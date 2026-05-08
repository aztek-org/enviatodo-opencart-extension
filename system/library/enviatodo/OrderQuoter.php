<?php
namespace Opencart\System\Library\Enviatodo;
/**
 * Class OrderQuoter
 *
 * Server-side helper that builds the same `Api/rates_client` payload the
 * storefront uses, but starting from a placed `oc_order` instead of the
 * cart. Used by the admin order panel to re-quote a shipment before
 * generating a label (which requires a fresh transaction.uuid).
 *
 * Returns the raw transaction uuid + the list of rates so the caller
 * can either match the saved shipping_code or surface the rates to the
 * admin user for manual selection.
 *
 * @package Opencart\System\Library\Enviatodo
 */
class OrderQuoter {
	/**
	 * @return array{uuid:string, rates:array<int,array<string,mixed>>, request:array<string,mixed>, response:array<string,mixed>}
	 */
	public static function quote(\Opencart\System\Engine\Registry $registry, int $order_id, ?array $origin = null): array {
		$config = $registry->get('config');
		$db     = $registry->get('db');
		$load   = $registry->get('load');

		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/AddressMapper.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/PackageBuilder.php';

		$load->model('sale/order');
		$order = (array)$registry->get('model_sale_order')->getOrder($order_id);

		if (!$order) {
			throw new \RuntimeException('Order not found.');
		}

		if ($origin === null) {
			$origin = self::resolveOrigin($db, (int)$config->get('shipping_enviatodo_default_origin_id'));
		}

		if (!$origin) {
			throw new \RuntimeException('No origin configured. Add one in EnviaTodo › Origins.');
		}

		$origin['state_code'] = self::lookupStateCode($db, (string)($origin['country'] ?? 'MX'), (string)($origin['state'] ?? ''));

		$load->model('localisation/country');
		$load->model('localisation/zone');

		$country = (array)$registry->get('model_localisation_country')->getCountry((int)$order['shipping_country_id']);
		$zone    = (array)$registry->get('model_localisation_zone')->getZone((int)$order['shipping_zone_id']);

		$destAddress = [
			'firstname' => $order['shipping_firstname'] ?? '',
			'lastname'  => $order['shipping_lastname']  ?? '',
			'company'   => $order['shipping_company']   ?? '',
			'address_1' => $order['shipping_address_1'] ?? '',
			'address_2' => $order['shipping_address_2'] ?? '',
			'city'      => $order['shipping_city']      ?? '',
			'postcode'  => $order['shipping_postcode']  ?? '',
			'telephone' => $order['telephone']          ?? '',
			'email'     => $order['email']              ?? '',
			'zone_id'   => (int)$order['shipping_zone_id'],
			'country_id'=> (int)$order['shipping_country_id'],
		];

		$originPayload = AddressMapper::fromOrigin($origin);
		$destPayload   = AddressMapper::fromCheckout($destAddress, $country, $zone);

		if (trim($destPayload['zip_code']) === '' || trim($destPayload['country_code']) === '') {
			throw new \RuntimeException('Order shipping address missing postal code or country.');
		}

		$products       = self::loadOrderProducts($db, $order_id);
		$declaredValue  = (float)$order['total'];
		$package        = PackageBuilder::buildAggregate(
			$products,
			$db,
			$registry->get('weight'),
			$registry->get('length'),
			$declaredValue
		);

		$body = [
			'type'   => 'order',
			'quotes' => [
				'shipping_type' => '1',
				'quantity'      => 1,
				'origin'        => $originPayload,
				'destination'   => $destPayload,
				'package'       => $package,
			],
		];

		$environment = (string)($config->get('shipping_enviatodo_environment') ?? 'sandbox');
		$token       = $environment === 'production'
			? (string)$config->get('shipping_enviatodo_token_production')
			: (string)$config->get('shipping_enviatodo_token_sandbox');
		$baseOverride = $config->get('shipping_enviatodo_base_url_override');

		if ($token === '') {
			throw new \RuntimeException('EnviaTodo token is empty for the selected environment.');
		}

		$logger = new Logger($db, (string)$config->get('shipping_enviatodo_log_level'));
		$client = new Client(
			$token,
			$environment,
			$logger,
			$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
		);

		$response = $client->post('Api/rates_client', $body);

		$data  = is_array($response['data'] ?? null) ? $response['data'] : [];
		$uuid  = (string)($data['transaction']['uuid'] ?? '');
		$rates = [];

		foreach ((array)($data['rates'] ?? []) as $r) {
			if (!is_array($r) || ($r['status'] ?? true) === false) {
				continue;
			}

			$cost = 0.0;
			foreach ((array)($r['charges'] ?? []) as $charge) {
				if (is_array($charge) && (string)($charge['type'] ?? '') === 'base') {
					$cost = (float)($charge['total'] ?? 0);
					break;
				}
			}

			$rates[] = [
				'provider_id'         => (string)($r['provider_id'] ?? ''),
				'provider_service_id' => (string)($r['provider_service_id'] ?? ''),
				'service_name'        => (string)($r['service_name'] ?? ''),
				'via_transport'       => (string)($r['via_transport'] ?? ''),
				'estimated_date'      => (string)($r['estimated_date'] ?? ''),
				'cost'                => $cost,
			];
		}

		return [
			'uuid'     => $uuid,
			'rates'    => $rates,
			'request'  => $body,
			'response' => $response,
			'origin'   => $origin,
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function loadOrderProducts(object $db, int $order_id): array {
		$query = $db->query(
			"SELECT op.`product_id`, op.`quantity`, p.`weight`, p.`weight_class_id` "
			. "FROM `" . DB_PREFIX . "order_product` op "
			. "LEFT JOIN `" . DB_PREFIX . "product` p ON p.`product_id` = op.`product_id` "
			. "WHERE op.`order_id` = " . (int)$order_id
		);

		$out = [];
		foreach ($query->rows as $row) {
			$qty       = max(1, (int)$row['quantity']);
			$weightOne = (float)($row['weight'] ?? 0);
			$out[] = [
				'product_id'      => (int)$row['product_id'],
				'quantity'        => $qty,
				'weight'          => $weightOne * $qty,
				'weight_class_id' => (int)($row['weight_class_id'] ?? 0),
			];
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function resolveOrigin(object $db, int $default_id): ?array {
		if ($default_id > 0) {
			$row = $db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` WHERE `origin_id` = " . $default_id);
			if ($row->num_rows) {
				return (array)$row->row;
			}
		}

		$row = $db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` ORDER BY `is_default` DESC, `origin_id` ASC LIMIT 1");

		return $row->num_rows ? (array)$row->row : null;
	}

	private static function lookupStateCode(object $db, string $countryIso, string $stateName): string {
		$countryIso = strtoupper(trim($countryIso));
		$stateName  = trim($stateName);

		if ($countryIso === '' || $stateName === '') {
			return '';
		}

		$row = $db->query(
			"SELECT z.`code` FROM `" . DB_PREFIX . "zone` z "
			. "INNER JOIN `" . DB_PREFIX . "country` c ON c.`country_id` = z.`country_id` "
			. "INNER JOIN `" . DB_PREFIX . "zone_description` zd ON zd.`zone_id` = z.`zone_id` "
			. "WHERE UPPER(c.`iso_code_2`) = '" . $db->escape($countryIso) . "' "
			. "AND zd.`name` = '" . $db->escape($stateName) . "' "
			. "ORDER BY zd.`language_id` ASC LIMIT 1"
		);

		return $row->num_rows ? (string)$row->row['code'] : '';
	}

	/**
	 * Parse `enviatodo.<provider_id>_<service_id>` shipping codes.
	 *
	 * @return array{provider_id:string, provider_service_id:string}|null
	 */
	public static function parseShippingCode(string $code): ?array {
		if (strpos($code, 'enviatodo.') !== 0) {
			return null;
		}

		$rest  = substr($code, strlen('enviatodo.'));
		$parts = explode('_', $rest, 2);

		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
			return null;
		}

		return [
			'provider_id'         => $parts[0],
			'provider_service_id' => $parts[1],
		];
	}
}
