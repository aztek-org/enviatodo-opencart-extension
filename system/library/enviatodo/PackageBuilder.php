<?php
namespace Opencart\System\Library\Enviatodo;
/**
 * Class PackageBuilder
 *
 * Translates a cart payload into the package array expected by
 * envia.com /Api/rates_client (`quotes.package`).
 *
 * Strategies:
 *  - "aggregate" — sum weight; bounding box uses
 *      max(length) × max(width) × sum(qty * height) across cart lines.
 *      Returns one package.
 *  - "per_item"  — reserved for Phase 6 (multi-package shipments).
 *
 * Units handling:
 *  enviatodo expects KG / CM. We convert from each product's
 *  weight_class_id / length_class_id using OpenCart's $weight / $length
 *  libraries, looking up the kg / cm class IDs from the unit column.
 *  Products without dims fall back to a 10 × 10 × 10 box, which is the
 *  carrier minimum charged volume.
 *
 * @package Opencart\System\Library\Enviatodo
 */
class PackageBuilder {
	public const FALLBACK_DIM_CM    = 10.0;
	public const FALLBACK_WEIGHT_KG = 0.5;

	/**
	 * @param array<int, array<string, mixed>> $cartProducts  Result of $this->cart->getProducts().
	 * @param object $db                                       OpenCart DB registry instance.
	 * @param object $weight                                   $registry->get('weight')
	 * @param object $length                                   $registry->get('length')
	 *
	 * @return array<string, mixed> Single package payload (KG / CM).
	 */
	public static function buildAggregate(array $cartProducts, object $db, object $weight, object $length, float $declaredValue = 0.0): array {
		$kgClass = self::lookupClassIdByUnit($db, 'weight_class_description', 'weight_class_id', 'kg');
		$cmClass = self::lookupClassIdByUnit($db, 'length_class_description', 'length_class_id', 'cm');

		$totalWeightKg = 0.0;
		$maxL          = 0.0;
		$maxW          = 0.0;
		$sumH          = 0.0;
		$qty           = 0;

		foreach ($cartProducts as $row) {
			$productId = (int)($row['product_id'] ?? 0);
			$quantity  = max(1, (int)($row['quantity'] ?? 1));

			$dims = self::getProductDims($db, $productId);

			$weightClass = (int)($row['weight_class_id'] ?? 0);
			$lengthClass = (int)($dims['length_class_id'] ?? 0);
			$rowWeight   = (float)($row['weight'] ?? 0); // already qty-multiplied by Cart

			$weightKg = $kgClass > 0 && $weightClass > 0 && $rowWeight > 0
				? (float)$weight->convert($rowWeight, $weightClass, $kgClass)
				: 0.0;

			$L = self::convertLen($length, (float)($dims['length'] ?? 0), $lengthClass, $cmClass);
			$W = self::convertLen($length, (float)($dims['width']  ?? 0), $lengthClass, $cmClass);
			$H = self::convertLen($length, (float)($dims['height'] ?? 0), $lengthClass, $cmClass);

			if ($L <= 0) $L = self::FALLBACK_DIM_CM;
			if ($W <= 0) $W = self::FALLBACK_DIM_CM;
			if ($H <= 0) $H = self::FALLBACK_DIM_CM;

			$totalWeightKg += $weightKg > 0 ? $weightKg : (self::FALLBACK_WEIGHT_KG * $quantity);
			$maxL           = max($maxL, $L);
			$maxW           = max($maxW, $W);
			$sumH          += $H * $quantity;
			$qty           += $quantity;
		}

		if ($totalWeightKg <= 0) $totalWeightKg = self::FALLBACK_WEIGHT_KG;
		if ($maxL <= 0) $maxL = self::FALLBACK_DIM_CM;
		if ($maxW <= 0) $maxW = self::FALLBACK_DIM_CM;
		if ($sumH <= 0) $sumH = self::FALLBACK_DIM_CM;

		$weightOut = round($totalWeightKg, 2);

		// enviatodo expects amount_pkg in cents and rejects 0; floor to 100 (1 peso) so rate calls always succeed.
		$amountPkg = max(100, (int)round($declaredValue * 100));

		return [
			'name'              => '',
			'product_type'      => '01010101',
			'unit_type'         => 'X1A',
			'package_content'   => 'Mercancia general',
			'amount_pkg'        => (string)$amountPkg,
			'height'            => round($sumH, 2),
			'width'             => round($maxW, 2),
			'length'            => round($maxL, 2),
			'weight'            => $weightOut,
			'id'                => '',
			'package_type_id'   => '1',
			'real_weight'       => number_format($weightOut, 2, '.', ''),
			'volumetric_weight' => number_format(max(1.0, ($maxL * $maxW * $sumH) / 5000.0), 2, '.', ''),
			'bill_weight'       => number_format($weightOut, 2, '.', ''),
			'default_pkg'       => '0',
			'product_quantity'  => (string)$qty,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getProductDims(object $db, int $productId): array {
		if ($productId <= 0) {
			return [];
		}

		$query = $db->query("SELECT `length`, `width`, `height`, `length_class_id` FROM `" . DB_PREFIX . "product` WHERE `product_id` = " . $productId);

		return $query->num_rows ? (array)$query->row : [];
	}

	private static function lookupClassIdByUnit(object $db, string $table, string $idField, string $unit): int {
		$rows = $db->query("SELECT `" . $idField . "` AS id FROM `" . DB_PREFIX . $table . "` WHERE LOWER(`unit`) = '" . $db->escape(strtolower($unit)) . "' LIMIT 1");

		return $rows->num_rows ? (int)$rows->row['id'] : 0;
	}

	private static function convertLen(object $length, float $value, int $from, int $to): float {
		if ($value <= 0 || $from <= 0 || $to <= 0) {
			return $value;
		}

		return (float)$length->convert($value, $from, $to);
	}
}
