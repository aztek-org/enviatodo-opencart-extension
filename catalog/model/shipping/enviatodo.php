<?php
namespace Opencart\Catalog\Model\Extension\Enviatodo\Shipping;
/**
 * Class Enviatodo
 *
 * Storefront shipping quote provider. Loaded by OpenCart's checkout via:
 *   $this->load->model('extension/enviatodo/shipping/enviatodo');
 *
 * Calls enviatodo.com Api/rates_client with the configured default origin
 * and the cart's destination address, then returns one OpenCart
 * shipping option per rate option (carrier × service) returned by the
 * API. Results are cached for 5 minutes per (origin + destination +
 * package) hash so that re-renders of the checkout don't hammer the API.
 *
 * @package Opencart\Catalog\Model\Extension\Enviatodo\Shipping
 */
class Enviatodo extends \Opencart\System\Engine\Model {
	public const CACHE_TTL_SECONDS = 300;

	/**
	 * @param array<string, mixed> $address Destination address (country_id, zone_id, postcode, ...)
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$this->load->model('localisation/geo_zone');

		$geo_zone_id = (int)$this->config->get('shipping_enviatodo_geo_zone_id');

		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/AddressMapper.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/PackageBuilder.php';

		if ($geo_zone_id) {
			$results = $this->model_localisation_geo_zone->getGeoZone(
				$geo_zone_id,
				(int)($address['country_id'] ?? 0),
				(int)($address['zone_id'] ?? 0)
			);

			if (!$results) {
				$this->diagnose('getQuote.skip', 'destination ' . (int)($address['country_id'] ?? 0) . '/' . (int)($address['zone_id'] ?? 0) . ' is outside geo_zone ' . $geo_zone_id);
				return [];
			}
		}

		$origin = $this->resolveOrigin();

		if (!$origin) {
			$this->diagnose('getQuote.skip', 'no origin configured in oc_enviatodo_origin');
			return [];
		}

		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		$country = (array)$this->model_localisation_country->getCountry((int)($address['country_id'] ?? 0));
		$zone    = (array)$this->model_localisation_zone->getZone((int)($address['zone_id'] ?? 0));

		// Derive a state_code for the origin from oc_zone (origins store only the state name).
		$origin['state_code'] = $this->lookupStateCode((string)($origin['country'] ?? 'MX'), (string)($origin['state'] ?? ''));

		$originPayload = \Opencart\System\Library\Enviatodo\AddressMapper::fromOrigin($origin);
		$destPayload   = \Opencart\System\Library\Enviatodo\AddressMapper::fromCheckout($address, $country, $zone);

		if (trim($destPayload['zip_code']) === '' || trim($destPayload['country_code']) === '') {
			// Not enough info to quote yet — checkout still in early step.
			return [];
		}

		$declaredValue = (float)($this->cart->getSubTotal() ?? 0);

		$package = \Opencart\System\Library\Enviatodo\PackageBuilder::buildAggregate(
			$this->cart->getProducts(),
			$this->db,
			$this->weight,
			$this->length,
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

		$cacheKey = 'enviatodo.rates.' . md5(json_encode($body, JSON_UNESCAPED_UNICODE));
		$rates    = $this->cacheGet($cacheKey);

		if ($rates === null) {
			try {
				$rates = $this->fetchRates($body);
				$this->cacheSet($cacheKey, $rates);
			} catch (\Throwable $e) {
				$this->diagnose('rates_client.exception', $e->getMessage());
				return [];
			}
		}

		if (!$rates) {
			return [];
		}

		$tax_class_id = (int)$this->config->get('shipping_enviatodo_tax_class_id');

		$quote_data = [];

		foreach ($rates as $rate) {
			$key  = $this->rateKey($rate);
			$cost = (float)($rate['cost'] ?? 0);

			if ($key === '' || $cost <= 0) {
				continue;
			}

			$title = $this->rateTitle($rate);

			$quote_data[$key] = [
				'code'         => 'enviatodo.' . $key,
				'name'         => $title,
				'cost'         => $cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format(
					$this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')),
					$this->session->data['currency']
				),
			];
		}

		uasort($quote_data, static function ($a, $b) {
			return $a['cost'] <=> $b['cost'];
		});

		if (!$quote_data) {
			return [];
		}

		return [
			'code'       => 'enviatodo',
			'name'       => $this->language->get('heading_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_enviatodo_sort_order'),
			'error'      => false,
		];
	}

	/**
	 * Pulls origins from oc_enviatodo_origin and picks the configured
	 * default. If no default is flagged we fall back to the first row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function resolveOrigin(): ?array {
		$default_id = (int)$this->config->get('shipping_enviatodo_default_origin_id');

		if ($default_id > 0) {
			$row = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` WHERE `origin_id` = " . $default_id);

			if ($row->num_rows) {
				return (array)$row->row;
			}
		}

		$row = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` ORDER BY `is_default` DESC, `origin_id` ASC LIMIT 1");

		return $row->num_rows ? (array)$row->row : null;
	}

	/**
	 * Look up the matching `oc_zone.code` for the origin's state name so we
	 * can populate `state_code` (enviatodo requires it for MX rates).
	 */
	private function lookupStateCode(string $countryIso, string $stateName): string {
		$countryIso = strtoupper(trim($countryIso));
		$stateName  = trim($stateName);

		if ($countryIso === '' || $stateName === '') {
			return '';
		}

		$row = $this->db->query(
			"SELECT z.`code` FROM `" . DB_PREFIX . "zone` z "
			. "INNER JOIN `" . DB_PREFIX . "country` c ON c.`country_id` = z.`country_id` "
			. "INNER JOIN `" . DB_PREFIX . "zone_description` zd ON zd.`zone_id` = z.`zone_id` "
			. "WHERE UPPER(c.`iso_code_2`) = '" . $this->db->escape($countryIso) . "' "
			. "AND zd.`name` = '" . $this->db->escape($stateName) . "' "
			. "ORDER BY zd.`language_id` ASC LIMIT 1"
		);

		return $row->num_rows ? (string)$row->row['code'] : '';
	}

	/**
	 * Always-on diagnostic logger for silent early-returns. Writes at
	 * "error" level even when the user has set log_level=off, so admins
	 * can see why no quote appeared in oc_enviatodo_log / DIR_LOGS.
	 */
	private function diagnose(string $endpoint, string $message): void {
		try {
			$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, 'error');
			$logger->write('error', $endpoint, null, $message);
		} catch (\Throwable $e) {
			// Best-effort logging only.
		}
	}

	/**
	 * @param array<string, mixed> $body
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetchRates(array $body): array {
		$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
		$token       = $environment === 'production'
			? (string)$this->config->get('shipping_enviatodo_token_production')
			: (string)$this->config->get('shipping_enviatodo_token_sandbox');
		$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

		if ($token === '') {
			$diagLogger = new \Opencart\System\Library\Enviatodo\Logger($this->db, 'error');
			$diagLogger->write('error', 'rates_client.skip', null, 'token empty for environment=' . $environment . ' (set shipping_enviatodo_token_' . $environment . ' or change environment)');
			return [];
		}

		$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
		$client = new \Opencart\System\Library\Enviatodo\Client(
			$token,
			$environment,
			$logger,
			$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
		);

		$response = $client->post('Api/rates_client', $body);

		// enviatodo response: { success, data: { transaction, rates: [...], packages }, error, code }
		$data = is_array($response['data'] ?? null) ? $response['data'] : [];
		$rows = is_array($data['rates'] ?? null) ? $data['rates'] : [];

		$out = [];

		foreach ($rows as $r) {
			if (!is_array($r) || ($r['status'] ?? true) === false) {
				continue;
			}

			// Pick the "base" charge as the displayed cost. Insurance is optional and only applies when the customer opts in at label time.
			$cost = 0.0;

			foreach ((array)($r['charges'] ?? []) as $charge) {
				if (is_array($charge) && (string)($charge['type'] ?? '') === 'base') {
					$cost = (float)($charge['total'] ?? 0);
					break;
				}
			}

			if ($cost <= 0) {
				continue;
			}

			$out[] = [
				'provider_id'         => (string)($r['provider_id'] ?? ''),
				'provider_service_id' => (string)($r['provider_service_id'] ?? ''),
				'service_name'        => (string)($r['service_name'] ?? ''),
				'via_transport'       => (string)($r['via_transport'] ?? ''),
				'estimated_date'      => (string)($r['estimated_date'] ?? ''),
				'cost'                => $cost,
			];
		}

		return $out;
	}

	/**
	 * Stable, URL-safe key encoding the carrier + service so Phase 6
	 * can map the chosen option back to a provider_id / service_id.
	 *
	 * @param array<string, mixed> $rate
	 */
	private function rateKey(array $rate): string {
		$provider = (string)($rate['provider_id'] ?? '');
		$service  = (string)($rate['provider_service_id'] ?? '');

		if ($provider === '' || $service === '') {
			return '';
		}

		return $provider . '_' . $service;
	}

	/**
	 * @param array<string, mixed> $rate
	 */
	private function rateTitle(array $rate): string {
		$service       = trim((string)($rate['service_name'] ?? ''));
		$via           = trim((string)($rate['via_transport'] ?? ''));
		$estimatedDate = trim((string)($rate['estimated_date'] ?? ''));

		$title = $service !== '' ? $service : (string)$this->language->get('heading_title');

		if ($via !== '') {
			$title .= ' (' . ucfirst(strtolower($via)) . ')';
		}

		if ($estimatedDate !== '') {
			$days = $this->daysFromNow($estimatedDate);

			if ($days > 0) {
				$title .= ' — ' . sprintf((string)$this->language->get('text_eta_days'), (string)$days);
			}
		}

		return $title;
	}

	private function daysFromNow(string $dateStr): int {
		try {
			$target = new \DateTimeImmutable($dateStr);
			$now    = new \DateTimeImmutable('now');
		} catch (\Throwable $e) {
			return 0;
		}

		$diff = (int)ceil(($target->getTimestamp() - $now->getTimestamp()) / 86400);

		return max(0, $diff);
	}

	/**
	 * @return array<int, array<string, mixed>>|null
	 */
	private function cacheGet(string $key): ?array {
		try {
			$cached = $this->cache->get($key);
		} catch (\Throwable $e) {
			return null;
		}

		if (!is_array($cached) || !isset($cached['expires'], $cached['rates'])) {
			return null;
		}

		if ((int)$cached['expires'] < time()) {
			return null;
		}

		return is_array($cached['rates']) ? $cached['rates'] : null;
	}

	/**
	 * @param array<int, array<string, mixed>> $rates
	 */
	private function cacheSet(string $key, array $rates): void {
		try {
			$this->cache->set($key, [
				'rates'   => $rates,
				'expires' => time() + self::CACHE_TTL_SECONDS,
			]);
		} catch (\Throwable $e) {
			// best-effort cache; ignore failures.
		}
	}

	/**
	 * Returns the latest enviatodo_shipment row for the given OpenCart order
	 * id, or null. Used by the catalog tracking page (Phase 7).
	 *
	 * @return array<string, mixed>|null
	 */
	public function getShipmentByOrderId(int $order_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_shipment` WHERE `order_id` = " . (int)$order_id . " ORDER BY `shipment_id` DESC LIMIT 1");

		return $query->num_rows ? (array)$query->row : null;
	}
}
