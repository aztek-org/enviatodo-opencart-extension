<?php
namespace Opencart\Admin\Controller\Extension\Enviatodo\Shipping;
/**
 * Class Enviatodo
 *
 * Settings page for the Enviatodo.com shipping extension.
 *
 * @package Opencart\Admin\Controller\Extension\Enviatodo\Shipping
 */
class Enviatodo extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$this->document->setTitle($this->language->get('heading_title'));

		// Idempotently make sure the order_info_before listener is registered
		// for installs that pre-date Phase 6.
		$this->ensureEvents();

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/enviatodo/shipping/enviatodo', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/enviatodo/shipping/enviatodo.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$keys = [
			'status', 'environment',
			'token_sandbox', 'token_production',
			'client_id',
			'base_url_override',
			'default_origin_id', 'package_strategy',
			'geo_zone_id', 'tax_class_id', 'sort_order',
			'log_level'
		];

		foreach ($keys as $k) {
			$data['shipping_enviatodo_' . $k] = $this->config->get('shipping_enviatodo_' . $k);
		}

		if ($data['shipping_enviatodo_environment'] === null) {
			$data['shipping_enviatodo_environment'] = 'sandbox';
		}
		if ($data['shipping_enviatodo_package_strategy'] === null) {
			$data['shipping_enviatodo_package_strategy'] = 'aggregate';
		}
		if ($data['shipping_enviatodo_log_level'] === null) {
			$data['shipping_enviatodo_log_level'] = 'error';
		}

		$data['test_connection'] = $this->url->link('extension/enviatodo/shipping/enviatodo.testConnection', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('extension/enviatodo/shipping/enviatodo');
		$data['origins']  = $this->model_extension_enviatodo_shipping_enviatodo->getOrigins();
		$data['carriers'] = $this->model_extension_enviatodo_shipping_enviatodo->getCarriers();
		$data['logs']     = $this->model_extension_enviatodo_shipping_enviatodo->getLogs(50);

		$data['address_sources'] = $this->buildAddressSources();

		$data['origin_save']      = $this->url->link('extension/enviatodo/shipping/enviatodo.saveOrigin',     'user_token=' . $this->session->data['user_token'], true);
		$data['origin_get']       = $this->url->link('extension/enviatodo/shipping/enviatodo.getOrigin',      'user_token=' . $this->session->data['user_token'], true);
		$data['origin_delete']    = $this->url->link('extension/enviatodo/shipping/enviatodo.deleteOrigin',   'user_token=' . $this->session->data['user_token'], true);
		$data['carriers_refresh'] = $this->url->link('extension/enviatodo/shipping/enviatodo.refreshCarriers', 'user_token=' . $this->session->data['user_token'], true);

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/enviatodo/shipping/enviatodo', $data));
	}

	public function save(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$post        = $this->request->post;
			$environment = (string)($post['shipping_enviatodo_environment'] ?? 'sandbox');
			$tokenKey    = $environment === 'production' ? 'shipping_enviatodo_token_production' : 'shipping_enviatodo_token_sandbox';
			$status      = (int)($post['shipping_enviatodo_status'] ?? 0);

			if ($status === 1 && trim((string)($post[$tokenKey] ?? '')) === '') {
				$json['error'] = sprintf($this->language->get('error_token_for_env'), $environment);
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_enviatodo', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Calls enviatodo.com GET Api/get_client_balance using the token
	 * for the currently selected environment. Used by the admin
	 * "Test connection" button to surface auth/connectivity errors
	 * before the storefront tries to quote.
	 */
	public function testConnection(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$environment = $this->request->post['shipping_enviatodo_environment'] ?? $this->request->post['environment'] ?? $this->config->get('shipping_enviatodo_environment') ?? 'sandbox';
			$token       = $environment === 'production'
				? ($this->request->post['shipping_enviatodo_token_production'] ?? $this->config->get('shipping_enviatodo_token_production'))
				: ($this->request->post['shipping_enviatodo_token_sandbox']    ?? $this->config->get('shipping_enviatodo_token_sandbox'));
			$baseOverride = $this->request->post['shipping_enviatodo_base_url_override'] ?? $this->config->get('shipping_enviatodo_base_url_override');

			if (!$token) {
				$json['error'] = $this->language->get('error_token_missing');
			}
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

			$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
			$client = new \Opencart\System\Library\Enviatodo\Client((string)$token, (string)$environment, $logger, $baseOverride !== null ? (string)$baseOverride : null);

			try {
				$response = $client->get('Api/get_client_balance');

				$balance = $response['data']['balance']
					?? $response['balance']
					?? (is_scalar($response['data'] ?? null) ? $response['data'] : null);

				if ($balance === null) {
					$balance = '?';
				}

				$json['success']  = sprintf($this->language->get('text_test_ok'), $balance);
				$json['base_url'] = $client->baseUrl();
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Origin CRUD — list lives in the Origins tab; this method backs
	 * the "Edit" pre-fill (returns one row as JSON).
	 */
	public function getOrigin(): void {
		$json = [];

		if (!$this->user->hasPermission('access', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/enviatodo/shipping/enviatodo');

			$origin_id = (int)($this->request->get['origin_id'] ?? 0);
			$origin    = $this->model_extension_enviatodo_shipping_enviatodo->getOrigin($origin_id);

			if (!$origin) {
				$json['error'] = $this->language->get('error_origin_not_found');
			} else {
				$json['origin'] = $origin;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Insert (origin_id=0) or update an origin from the modal form.
	 */
	public function saveOrigin(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$post      = $this->request->post;
		$origin_id = (int)($post['origin_id'] ?? 0);

		if (!$json) {
			$errors = [];

			if (trim((string)($post['name']        ?? '')) === '') $errors[] = $this->language->get('error_origin_name');
			if (trim((string)($post['postal_code'] ?? '')) === '') $errors[] = $this->language->get('error_origin_postal_code');
			if (trim((string)($post['country']     ?? '')) === '') $errors[] = $this->language->get('error_origin_country');

			if ($errors) {
				$json['error'] = implode(' ', $errors);
			}
		}

		if (!$json) {
			$this->load->model('extension/enviatodo/shipping/enviatodo');

			if ($origin_id > 0) {
				$this->model_extension_enviatodo_shipping_enviatodo->editOrigin($origin_id, $post);
				$json['origin_id'] = $origin_id;
			} else {
				$json['origin_id'] = $this->model_extension_enviatodo_shipping_enviatodo->addOrigin($post);
			}

			$json['success'] = $this->language->get('text_origin_saved');
			$json['origins'] = $this->model_extension_enviatodo_shipping_enviatodo->getOrigins();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteOrigin(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$origin_id = (int)($this->request->post['origin_id'] ?? 0);

			$this->load->model('extension/enviatodo/shipping/enviatodo');
			$this->model_extension_enviatodo_shipping_enviatodo->deleteOrigin($origin_id);

			$json['success'] = $this->language->get('text_origin_deleted');
			$json['origins'] = $this->model_extension_enviatodo_shipping_enviatodo->getOrigins();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Pulls the carrier list from enviatodo.com (Api/get_parcel_service)
	 * and replaces the local cache. Used by the Carriers tab "Refresh"
	 * button.
	 */
	public function refreshCarriers(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
			$token       = $environment === 'production'
				? (string)$this->config->get('shipping_enviatodo_token_production')
				: (string)$this->config->get('shipping_enviatodo_token_sandbox');
			$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

			if (!$token) {
				$json['error'] = $this->language->get('error_token_missing');
			}
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

			$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
			$client = new \Opencart\System\Library\Enviatodo\Client($token, $environment, $logger, $baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null);

			try {
				$response = $client->get('Api/get_parcel_service');

				$rows = $response['data'] ?? $response['parcels'] ?? $response['response'] ?? [];
				if (!is_array($rows)) {
					$rows = [];
				}

				$this->load->model('extension/enviatodo/shipping/enviatodo');
				$this->model_extension_enviatodo_shipping_enviatodo->clearCarriers();

				$count = 0;

				foreach ($rows as $row) {
					if (!is_array($row)) continue;

					$code = (string)($row['provider_id'] ?? $row['code'] ?? $row['provider_code'] ?? $row['id'] ?? '');

					if ($code === '') continue;

					$this->model_extension_enviatodo_shipping_enviatodo->upsertCarrier($code, [
						'name'          => (string)($row['trade_name'] ?? $row['name'] ?? $row['provider'] ?? $row['nombre'] ?? $code),
						'services_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
					]);

					$count++;
				}

				$json['success']  = sprintf($this->language->get('text_carriers_refreshed'), $count);
				$json['carriers'] = $this->model_extension_enviatodo_shipping_enviatodo->getCarriers();
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Build the "Import from…" options shown in the origin modal.
	 * Pulls from System > Configuration > Store and System > Localization > Locations
	 * and pre-shapes each entry into the modal's field names so the JS just copies values.
	 *
	 * @return array<int,array{id:string,label:string,prefill:array<string,string|int>}>
	 */
	private function buildAddressSources(): array {
		$sources = [];

		$this->load->model('setting/store');
		$this->load->model('setting/setting');
		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');
		$this->load->model('localisation/location');

		$stores   = [['store_id' => 0, 'name' => $this->config->get('config_name')]];
		$stores   = array_merge($stores, (array)$this->model_setting_store->getStores());

		foreach ($stores as $store) {
			$store_id = (int)($store['store_id'] ?? 0);
			$cfg      = $store_id === 0
				? $this->getDefaultStoreConfig()
				: (array)$this->model_setting_setting->getSetting('config', $store_id);

			$prefill = $this->shapeAddressPrefill([
				'name'      => $cfg['config_name']      ?? ($store['name'] ?? ''),
				'address'   => $cfg['config_address']   ?? '',
				'telephone' => $cfg['config_telephone'] ?? '',
				'email'     => $cfg['config_email']     ?? '',
				'country_id'=> (int)($cfg['config_country_id'] ?? 0),
				'zone_id'   => (int)($cfg['config_zone_id']    ?? 0),
				'postcode'  => $cfg['config_postcode']  ?? '',
			]);

			$label = sprintf('%s: %s', $this->language->get('text_source_store'), $prefill['name'] ?: ('#' . $store_id));

			$sources[] = [
				'id'      => 'store-' . $store_id,
				'label'   => $label,
				'prefill' => $prefill,
			];
		}

		$locations = (array)$this->model_localisation_location->getLocations();

		foreach ($locations as $loc) {
			$location_id = (int)($loc['location_id'] ?? 0);
			$full        = $location_id > 0 ? (array)$this->model_localisation_location->getLocation($location_id) : $loc;

			$prefill = $this->shapeAddressPrefill([
				'name'      => $full['name']      ?? ($loc['name']    ?? ''),
				'address'   => $full['address']   ?? ($loc['address'] ?? ''),
				'telephone' => $full['telephone'] ?? '',
				'email'     => '',
				'country_id'=> 0,
				'zone_id'   => 0,
				'postcode'  => '',
			]);

			$sources[] = [
				'id'      => 'location-' . $location_id,
				'label'   => sprintf('%s: %s', $this->language->get('text_source_location'), $prefill['name'] ?: ('#' . $location_id)),
				'prefill' => $prefill,
			];
		}

		return $sources;
	}

	/**
	 * Read default-store config rows (store_id=0) directly. The setting model
	 * filters by group, so it works for store_id>0 only when a row exists; the
	 * default store always lives under store_id=0 and is also exposed via $this->config.
	 *
	 * @return array<string,string>
	 */
	private function getDefaultStoreConfig(): array {
		$keys = [
			'config_name', 'config_address', 'config_telephone', 'config_email',
			'config_country_id', 'config_zone_id', 'config_postcode',
		];

		$out = [];

		foreach ($keys as $k) {
			$out[$k] = (string)$this->config->get($k);
		}

		return $out;
	}

	/**
	 * Convert raw store/location data into the field names used by the origin modal.
	 * The OC store config keeps the postal address as a single free-text field, so
	 * we copy it into "street" and let the user split it if they care; postal_code,
	 * country (ISO-2) and state come from structured fields when available.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,string>
	 */
	private function shapeAddressPrefill(array $row): array {
		$country_iso = '';
		$state       = '';

		$country_id = (int)($row['country_id'] ?? 0);
		if ($country_id > 0) {
			$country = $this->model_localisation_country->getCountry($country_id);
			if ($country) {
				$country_iso = (string)($country['iso_code_2'] ?? '');
			}
		}

		$zone_id = (int)($row['zone_id'] ?? 0);
		if ($zone_id > 0) {
			$zone = $this->model_localisation_zone->getZone($zone_id);
			if ($zone) {
				$state = (string)($zone['name'] ?? '');
			}
		}

		$address = trim((string)($row['address'] ?? ''));
		$postcode = trim((string)($row['postcode'] ?? ''));

		// If the free-text address has a 5-digit postal code embedded and we don't
		// already have one from a structured field, lift it out for convenience.
		if ($postcode === '' && $address !== '' && preg_match('/\b(\d{5})\b/', $address, $m)) {
			$postcode = $m[1];
		}

		return [
			'name'        => trim((string)($row['name'] ?? '')),
			'contact'     => trim((string)($row['name'] ?? '')),
			'phone'       => trim((string)($row['telephone'] ?? '')),
			'email'       => trim((string)($row['email'] ?? '')),
			'street'      => $address,
			'number'      => '',
			'district'    => '',
			'city'        => '',
			'state'       => $state,
			'postal_code' => $postcode,
			'country'     => $country_iso !== '' ? $country_iso : 'MX',
		];
	}

	/**
	 * Install hook — invoked by OpenCart when the extension is enabled
	 * via Extensions > Shipping > Install. Creates the four enviatodo
	 * tables idempotently and registers the admin event listeners.
	 */
	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			return;
		}

		$this->load->model('extension/enviatodo/shipping/enviatodo');
		$this->model_extension_enviatodo_shipping_enviatodo->install();

		$this->ensureEvents();
	}

	/**
	 * Uninstall hook. Drops the four enviatodo tables and removes events.
	 */
	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			return;
		}

		$this->load->model('extension/enviatodo/shipping/enviatodo');
		$this->model_extension_enviatodo_shipping_enviatodo->uninstall();

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('enviatodo_order_info');
		$this->model_setting_event->deleteEventByCode('enviatodo_order_info_catalog');
	}

	/**
	 * Idempotently registers the admin/view/sale/order_info/before event.
	 * Called from install() and (defensively) from index() so existing
	 * installations pick the listener up the next time the settings
	 * page is opened.
	 */
	private function ensureEvents(): void {
		$this->load->model('setting/event');

		$existing = $this->model_setting_event->getEventByCode('enviatodo_order_info');

		if (!$existing) {
			$this->model_setting_event->addEvent([
				'code'        => 'enviatodo_order_info',
				'description' => 'EnviaTodo: inject shipment tab into sale/order_info',
				'trigger'     => 'admin/view/sale/order_info/before',
				'action'      => 'extension/enviatodo/shipping/enviatodo.order_info_before',
				'status'      => true,
				'sort_order'  => 5,
			]);
		}

		// Phase 7 — catalog-side "Track shipment" link on account/order_info.
		$existing_catalog = $this->model_setting_event->getEventByCode('enviatodo_order_info_catalog');

		if (!$existing_catalog) {
			$this->model_setting_event->addEvent([
				'code'        => 'enviatodo_order_info_catalog',
				'description' => 'EnviaTodo: inject "Track shipment" link into catalog account/order_info',
				'trigger'     => 'catalog/view/account/order_info/before',
				'action'      => 'extension/enviatodo/shipping/enviatodo.order_info_before',
				'status'      => true,
				'sort_order'  => 5,
			]);
		}
	}

	// ---------- Phase 6 — Order shipment panel ----------

	/**
	 * Event listener attached to admin/view/sale/order_info/before.
	 * Appends an "EnviaTodo" tab to the order detail page.
	 *
	 * @param array<string, mixed> $data
	 */
	public function order_info_before(string &$route, array &$data): void {
		if (empty($this->request->get['order_id'])) {
			return;
		}

		if (!isset($data['tabs']) || !is_array($data['tabs'])) {
			return;
		}

		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$content = $this->renderShipmentPanel((int)$this->request->get['order_id']);

		if ($content === '') {
			return;
		}

		$data['tabs'][] = [
			'code'    => 'enviatodo',
			'title'   => $this->language->get('heading_title'),
			'content' => $content,
		];

		$this->load->language('sale/order');
	}

	/**
	 * AJAX endpoint that returns the panel HTML — called by JS after
	 * label/cancel/refresh actions to refresh the tab content.
	 */
	public function shipmentPanel(): void {
		if (!$this->user->hasPermission('access', 'extension/enviatodo/shipping/enviatodo')) {
			$this->response->addHeader('Content-Type: text/html; charset=utf-8');
			$this->response->setOutput('');
			return;
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		$this->response->addHeader('Content-Type: text/html; charset=utf-8');
		$this->response->setOutput($this->renderShipmentPanel($order_id));
	}

	private function renderShipmentPanel(int $order_id): string {
		if ($order_id <= 0) {
			return '';
		}

		$this->load->language('extension/enviatodo/shipping/enviatodo');
		$this->load->model('sale/order');
		$this->load->model('extension/enviatodo/shipping/enviatodo');

		$order = (array)$this->model_sale_order->getOrder($order_id);

		if (!$order) {
			return '';
		}

		$shipment = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

		$shipping_method_arr = is_array($order['shipping_method'] ?? null) ? $order['shipping_method'] : [];
		$shipping_code   = (string)($shipping_method_arr['code'] ?? '');
		$is_enviatodo    = strpos($shipping_code, 'enviatodo.') === 0;
		$shipping_method = (string)($shipping_method_arr['name'] ?? '');

		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/OrderQuoter.php';

		$saved_codes = \Opencart\System\Library\Enviatodo\OrderQuoter::parseShippingCode($shipping_code);

		$data = [
			'order_id'       => $order_id,
			'shipping_code'  => $shipping_code,
			'shipping_method'=> $shipping_method,
			'is_enviatodo'   => $is_enviatodo,
			'shipment'       => $shipment,
			'saved_provider' => $saved_codes['provider_id']         ?? '',
			'saved_service'  => $saved_codes['provider_service_id'] ?? '',
		];

		$token = $this->session->data['user_token'];

		$data['url_quote']    = $this->url->link('extension/enviatodo/shipping/enviatodo.shipmentQuote',    'user_token=' . $token, true);
		$data['url_generate'] = $this->url->link('extension/enviatodo/shipping/enviatodo.generateLabel',    'user_token=' . $token, true);
		$data['url_cancel']   = $this->url->link('extension/enviatodo/shipping/enviatodo.cancelShipment',   'user_token=' . $token, true);
		$data['url_download'] = $this->url->link('extension/enviatodo/shipping/enviatodo.downloadLabel',    'user_token=' . $token, true);
		$data['url_refresh']  = $this->url->link('extension/enviatodo/shipping/enviatodo.refreshTracking',  'user_token=' . $token, true);
		$data['url_panel']    = $this->url->link('extension/enviatodo/shipping/enviatodo.shipmentPanel',    'user_token=' . $token . '&order_id=' . $order_id, true);

		return $this->load->view('extension/enviatodo/shipping/_shipment_panel', $data);
	}

	/**
	 * Re-quote the order against `Api/rates_client` and return the
	 * available rates + transaction uuid for the admin to pick from.
	 */
	public function shipmentQuote(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_id = (int)($this->request->get['order_id'] ?? $this->request->post['order_id'] ?? 0);

		if (!$json && $order_id <= 0) {
			$json['error'] = $this->language->get('error_shipment_order_id');
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/OrderQuoter.php';

			try {
				$result = \Opencart\System\Library\Enviatodo\OrderQuoter::quote($this->registry, $order_id);

				usort($result['rates'], static function ($a, $b) {
					return $a['cost'] <=> $b['cost'];
				});

				$json['uuid']  = $result['uuid'];
				$json['rates'] = $result['rates'];
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Calls Api/create_order with the chosen rate (or the rate matching
	 * the saved shipping_code) and persists the resulting tracking info
	 * into oc_enviatodo_shipment.
	 */
	public function generateLabel(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_id            = (int)($this->request->post['order_id']            ?? 0);
		$provider_id         = (string)($this->request->post['provider_id']         ?? '');
		$provider_service_id = (string)($this->request->post['provider_service_id'] ?? '');
		$insurance           = !empty($this->request->post['insurance']);

		if (!$json && $order_id <= 0) {
			$json['error'] = $this->language->get('error_shipment_order_id');
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/OrderQuoter.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

			try {
				$quote = \Opencart\System\Library\Enviatodo\OrderQuoter::quote($this->registry, $order_id);

				if ($quote['uuid'] === '') {
					throw new \RuntimeException($this->language->get('error_no_uuid'));
				}

				if ($provider_id === '' || $provider_service_id === '') {
					$this->load->model('sale/order');
					$order  = (array)$this->model_sale_order->getOrder($order_id);
					$shipping_method_arr = is_array($order['shipping_method'] ?? null) ? $order['shipping_method'] : [];
					$parsed = \Opencart\System\Library\Enviatodo\OrderQuoter::parseShippingCode((string)($shipping_method_arr['code'] ?? ''));

					if ($parsed) {
						$provider_id         = $parsed['provider_id'];
						$provider_service_id = $parsed['provider_service_id'];
					}
				}

				$matched = null;
				foreach ($quote['rates'] as $rate) {
					if ($rate['provider_id'] === $provider_id && $rate['provider_service_id'] === $provider_service_id) {
						$matched = $rate;
						break;
					}
				}

				if (!$matched) {
					$json['error'] = $this->language->get('error_rate_not_found');
					$json['rates'] = $quote['rates'];
					$json['uuid']  = $quote['uuid'];

					$this->response->addHeader('Content-Type: application/json');
					$this->response->setOutput(json_encode($json));
					return;
				}

				$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
				$token       = $environment === 'production'
					? (string)$this->config->get('shipping_enviatodo_token_production')
					: (string)$this->config->get('shipping_enviatodo_token_sandbox');
				$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

				$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
				$client = new \Opencart\System\Library\Enviatodo\Client(
					$token,
					$environment,
					$logger,
					$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
				);

				$body = [
					'order' => [
						'type' => 'create_order',
						'data' => [
							'uuid'   => $quote['uuid'],
							'detail' => [
								'provider_id'         => $provider_id,
								'provider_service_id' => $provider_service_id,
								'insurance'           => (bool)$insurance,
							],
						],
					],
				];

				$response = $client->post('Api/create_order', $body);

				$summary = $this->extractShipmentSummary($response);

				$this->load->model('extension/enviatodo/shipping/enviatodo');

				$existing = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

				$record = [
					'order_id'        => $order_id,
					'origin_id'       => (int)($quote['origin']['origin_id'] ?? 0),
					'carrier'         => $provider_id . ' / ' . $provider_service_id,
					'service'         => $matched['service_name'] ?? '',
					'tracking_number' => $summary['tracking_number'],
					'label_url'       => $summary['label_url'],
					'status'          => $summary['status'],
					'request_json'    => json_encode($body, JSON_UNESCAPED_UNICODE),
					'response_json'   => json_encode($response, JSON_UNESCAPED_UNICODE),
				];

				if ($existing) {
					$this->model_extension_enviatodo_shipping_enviatodo->editShipment((int)$existing['shipment_id'], $record);
					$json['shipment_id'] = (int)$existing['shipment_id'];
				} else {
					$json['shipment_id'] = $this->model_extension_enviatodo_shipping_enviatodo->addShipment($record);
				}

				$json['success']         = $this->language->get('text_label_generated');
				$json['tracking_number'] = $summary['tracking_number'];
				$json['guide_id']        = $summary['guide_id'];
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Cancels a previously-generated label via Api/cancel_order.
	 */
	public function cancelShipment(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_id = (int)($this->request->post['order_id'] ?? 0);
		$reason   = trim((string)($this->request->post['reason'] ?? 'Cancelled from OpenCart admin.'));

		if (!$json && $order_id <= 0) {
			$json['error'] = $this->language->get('error_shipment_order_id');
		}

		if (!$json) {
			$this->load->model('extension/enviatodo/shipping/enviatodo');
			$shipment = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

			if (!$shipment || $shipment['tracking_number'] === '') {
				$json['error'] = $this->language->get('error_no_shipment');
			}
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

			try {
				$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
				$token       = $environment === 'production'
					? (string)$this->config->get('shipping_enviatodo_token_production')
					: (string)$this->config->get('shipping_enviatodo_token_sandbox');
				$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

				$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
				$client = new \Opencart\System\Library\Enviatodo\Client(
					$token,
					$environment,
					$logger,
					$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
				);

				$now = date('Y-m-d\TH:i:s');

				$body = [
					'tracking_ids' => [$shipment['tracking_number']],
					'date'         => [
						'cancelled_at' => $now,
						'refunded_at'  => $now,
					],
					'reason'       => [
						'value' => '7',
						'text'  => $reason !== '' ? $reason : 'Cancelled from OpenCart admin.',
					],
					'order_type'   => 'rates',
					'client_id'    => (string)($this->config->get('shipping_enviatodo_client_id') ?? ''),
				];

				$response = $client->post('Api/cancel_order', $body);

				$this->model_extension_enviatodo_shipping_enviatodo->editShipment((int)$shipment['shipment_id'], [
					'status'        => 'cancelled',
					'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE),
				]);

				$json['success'] = $this->language->get('text_shipment_cancelled');
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	/**
	 * Calls Api/download_guide_binaries and streams the label PDF
	 * directly to the browser. Idempotent — can be re-invoked safely.
	 */
	public function downloadLabel(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$order_id = (int)($this->request->get['order_id'] ?? $this->request->post['order_id'] ?? 0);

		if (!$this->user->hasPermission('access', 'extension/enviatodo/shipping/enviatodo')) {
			$this->respondPlain(403, $this->language->get('error_permission'));
			return;
		}

		if ($order_id <= 0) {
			$this->respondPlain(400, $this->language->get('error_shipment_order_id'));
			return;
		}

		$this->load->model('extension/enviatodo/shipping/enviatodo');
		$shipment = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

		if (!$shipment) {
			$this->respondPlain(404, $this->language->get('error_no_shipment'));
			return;
		}

		$guide_id = $this->extractGuideIdFromShipment($shipment);

		if ($guide_id === '') {
			$this->respondPlain(404, $this->language->get('error_no_guide'));
			return;
		}

		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

		try {
			$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
			$token       = $environment === 'production'
				? (string)$this->config->get('shipping_enviatodo_token_production')
				: (string)$this->config->get('shipping_enviatodo_token_sandbox');
			$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

			$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
			$client = new \Opencart\System\Library\Enviatodo\Client(
				$token,
				$environment,
				$logger,
				$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
			);

			$response = $client->post('Api/download_guide_binaries', ['guides' => [(int)$guide_id]]);

			$file = $response['data']['files'][0]['file'] ?? null;
			$b64  = is_array($file) ? (string)($file['binary'] ?? '') : '';
			$name = is_array($file) ? (string)($file['name'] ?? '') : '';

			if ($b64 === '') {
				$this->respondPlain(502, $this->language->get('error_no_label_url'));
				return;
			}

			$pdf = base64_decode($b64, true);
			if ($pdf === false || $pdf === '') {
				$this->respondPlain(502, $this->language->get('error_no_label_url'));
				return;
			}

			if ($name === '') {
				$name = 'enviatodo-' . $guide_id . '.pdf';
			}

			// Cache the filename so the panel can show "label is ready".
			$this->model_extension_enviatodo_shipping_enviatodo->editShipment((int)$shipment['shipment_id'], [
				'label_url' => $name,
			]);

			$this->response->addHeader('Content-Type: application/pdf');
			$this->response->addHeader('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
			$this->response->addHeader('Content-Length: ' . strlen($pdf));
			$this->response->setOutput($pdf);
		} catch (\Throwable $e) {
			$this->respondPlain(500, $e->getMessage());
		}
	}

	private function respondPlain(int $code, string $message): void {
		http_response_code($code);
		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput($message);
	}

	/**
	 * Refreshes the shipment status by calling Api/get_orders_filter
	 * over the last 30 days and matching by tracking_id.
	 */
	public function refreshTracking(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/enviatodo/shipping/enviatodo')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$order_id = (int)($this->request->post['order_id'] ?? $this->request->get['order_id'] ?? 0);

		if (!$json && $order_id <= 0) {
			$json['error'] = $this->language->get('error_shipment_order_id');
		}

		if (!$json) {
			$this->load->model('extension/enviatodo/shipping/enviatodo');
			$shipment = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

			if (!$shipment || $shipment['tracking_number'] === '') {
				$json['error'] = $this->language->get('error_no_shipment');
			}
		}

		if (!$json) {
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
			require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

			try {
				$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
				$token       = $environment === 'production'
					? (string)$this->config->get('shipping_enviatodo_token_production')
					: (string)$this->config->get('shipping_enviatodo_token_sandbox');
				$baseOverride = $this->config->get('shipping_enviatodo_base_url_override');

				$logger = new \Opencart\System\Library\Enviatodo\Logger($this->db, (string)$this->config->get('shipping_enviatodo_log_level'));
				$client = new \Opencart\System\Library\Enviatodo\Client(
					$token,
					$environment,
					$logger,
					$baseOverride !== null && $baseOverride !== '' ? (string)$baseOverride : null
				);

				$response = $client->post('Api/get_orders_filter', [
					'date_range' => [
						'start' => date('Y-m-d', strtotime('-30 days')),
						'end'   => date('Y-m-d', strtotime('+1 day')),
					],
					'provider' => 'all',
					'status'   => 'all',
					'user_id'  => 'all',
				]);

				$status = $this->matchStatusByTracking($response, (string)$shipment['tracking_number']);

				if ($status !== '') {
					$this->model_extension_enviatodo_shipping_enviatodo->editShipment((int)$shipment['shipment_id'], [
						'status' => $status,
					]);
				}

				$json['success'] = sprintf($this->language->get('text_tracking_refreshed'), $status !== '' ? $status : '—');
				$json['status']  = $status;
			} catch (\Throwable $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	/**
	 * Pulls tracking_id, internal guide_id and status from a real
	 * `Api/create_order` response. Field locations confirmed against
	 * sandbox: data.guides[0].{id, tracking_id, provider_id}.
	 *
	 * @param array<string, mixed> $response
	 *
	 * @return array{tracking_number:string, label_url:string, status:string, guide_id:string}
	 */
	private function extractShipmentSummary(array $response): array {
		$guide = [];

		if (isset($response['data']['guides'][0]) && is_array($response['data']['guides'][0])) {
			$guide = $response['data']['guides'][0];
		}

		$success = !empty($response['success']) && empty($response['error']);

		return [
			'tracking_number' => (string)($guide['tracking_id'] ?? ''),
			'label_url'       => '',
			'status'          => $success && !empty($guide['id']) ? 'created' : 'pending',
			'guide_id'        => (string)($guide['id'] ?? ''),
		];
	}

	/**
	 * @param array<string, mixed> $shipment
	 */
	private function extractGuideIdFromShipment(array $shipment): string {
		$decoded = json_decode((string)($shipment['response_json'] ?? '[]'), true);

		if (!is_array($decoded)) {
			return '';
		}

		return $this->extractShipmentSummary($decoded)['guide_id'];
	}

	/**
	 * Find the matching transaction in `Api/get_orders_filter` by
	 * tracking_id and return its `status_name` (or '').
	 *
	 * @param array<string, mixed> $response
	 */
	private function matchStatusByTracking(array $response, string $tracking): string {
		$rows = is_array($response['data'] ?? null) ? $response['data'] : [];

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$guideTid = (string)($row['order_detail']['order']['guide']['tracking_id'] ?? '');

			if ($guideTid !== '' && $guideTid === $tracking) {
				return (string)($row['status_name'] ?? '');
			}
		}

		return '';
	}
}
