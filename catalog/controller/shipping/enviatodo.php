<?php
namespace Opencart\Catalog\Controller\Extension\Enviatodo\Shipping;

/**
 * Catalog controller for the EnviaTodo extension.
 *
 * Phase 7 surface — exposes a customer-facing tracking page at:
 *   index.php?route=extension/enviatodo/shipping/enviatodo.track&order_id=N
 *
 * Also registers a `catalog/view/account/order_info/before` event listener
 * that injects a "Track shipment" link into the order detail page when the
 * order's shipping_code starts with `enviatodo.`.
 */
class Enviatodo extends \Opencart\System\Engine\Controller {
	/**
	 * Customer tracking page.
	 *
	 * Auth: must be logged in AND own the order.
	 */
	public function track(): void {
		$this->load->language('extension/enviatodo/shipping/enviatodo');

		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link(
				'extension/enviatodo/shipping/enviatodo.track',
				'language=' . $this->config->get('config_language')
				. '&order_id=' . (int)($this->request->get['order_id'] ?? 0),
				true
			);

			$this->response->redirect($this->url->link(
				'account/login',
				'language=' . $this->config->get('config_language'),
				true
			));

			return;
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		$this->load->model('account/order');
		$this->load->model('extension/enviatodo/shipping/enviatodo');

		$order_info = $this->model_account_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link(
				'account/order',
				'language=' . $this->config->get('config_language')
				. '&customer_token=' . ($this->session->data['customer_token'] ?? ''),
				true
			));

			return;
		}

		$shipping_code = '';

		if (!empty($order_info['shipping_method']) && is_array($order_info['shipping_method'])) {
			$shipping_code = (string)($order_info['shipping_method']['code'] ?? '');
		}

		$shipment = $this->model_extension_enviatodo_shipping_enviatodo->getShipmentByOrderId($order_id);

		// Always show the page, even if the shipment hasn't been created yet —
		// the customer just sees a "pending" state.
		$tracking = $shipment ? $this->fetchTracking((string)$shipment['tracking_number']) : null;

		$data['heading_title'] = sprintf($this->language->get('text_track_heading'), (int)$order_id);

		$this->document->setTitle($data['heading_title']);

		$language = $this->config->get('config_language');
		$customer_token = (string)($this->session->data['customer_token'] ?? '');

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'language=' . $language),
			],
			[
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', 'language=' . $language . '&customer_token=' . $customer_token),
			],
			[
				'text' => sprintf($this->language->get('text_order'), $order_id),
				'href' => $this->url->link('account/order.info', 'language=' . $language . '&customer_token=' . $customer_token . '&order_id=' . $order_id),
			],
			[
				'text' => $data['heading_title'],
				'href' => $this->url->link('extension/enviatodo/shipping/enviatodo.track', 'language=' . $language . '&order_id=' . $order_id),
			],
		];

		$data['order_id']      = (int)$order_info['order_id'];
		$data['order_status']  = (string)($order_info['order_status'] ?? '');
		$data['date_added']    = date($this->language->get('date_format_short'), strtotime((string)$order_info['date_added']));
		$data['shipping_method'] = (string)($order_info['shipping_method']['name'] ?? '');
		$data['shipping_code'] = $shipping_code;
		$data['is_enviatodo']  = $shipping_code !== '' && strpos($shipping_code, 'enviatodo.') === 0;

		$data['url_back'] = $this->url->link('account/order.info', 'language=' . $language . '&customer_token=' . $customer_token . '&order_id=' . $order_id);

		// Empty shipment placeholder
		$data['shipment'] = null;

		if ($shipment) {
			$data['shipment'] = [
				'status'          => (string)($shipment['status'] ?? ''),
				'tracking_number' => (string)($shipment['tracking_number'] ?? ''),
				'carrier'         => (string)($shipment['carrier'] ?? ''),
				'service'         => (string)($shipment['service'] ?? ''),
				'date_added'      => !empty($shipment['date_added']) ? date($this->language->get('date_format_short'), strtotime((string)$shipment['date_added'])) : '',
			];

			if ($tracking) {
				$data['shipment'] = array_merge($data['shipment'], $tracking);
			}
		}

		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/enviatodo/track', $data));
	}

	/**
	 * Calls Api/get_orders_filter and returns the public-safe subset of
	 * data for the matching tracking row, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	private function fetchTracking(string $tracking): ?array {
		if ($tracking === '') {
			return null;
		}

		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Logger.php';
		require_once DIR_EXTENSION . 'enviatodo/system/library/enviatodo/Client.php';

		try {
			$environment = (string)($this->config->get('shipping_enviatodo_environment') ?? 'sandbox');
			$token       = $environment === 'production'
				? (string)$this->config->get('shipping_enviatodo_token_production')
				: (string)$this->config->get('shipping_enviatodo_token_sandbox');

			if ($token === '') {
				return null;
			}

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
					'start' => date('Y-m-d', strtotime('-60 days')),
					'end'   => date('Y-m-d', strtotime('+1 day')),
				],
				'provider' => 'all',
				'status'   => 'all',
				'user_id'  => 'all',
			]);
		} catch (\Throwable $e) {
			return null;
		}

		$rows = is_array($response['data'] ?? null) ? $response['data'] : [];

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$guideTid = (string)($row['order_detail']['order']['guide']['tracking_id'] ?? '');

			if ($guideTid === '' || $guideTid !== $tracking) {
				continue;
			}

			$detail = is_array($row['order_detail']['order']['detail'] ?? null)
				? $row['order_detail']['order']['detail']
				: [];
			$guide  = is_array($row['order_detail']['order']['guide'] ?? null)
				? $row['order_detail']['order']['guide']
				: [];
			$reasons = is_array($row['order_detail']['reasons_for_cancellation'] ?? null)
				? $row['order_detail']['reasons_for_cancellation']
				: null;

			return [
				'status_name'      => (string)($row['status_name'] ?? ''),
				'created_at'       => (string)($row['created_at'] ?? ''),
				'estimated_date'   => (string)($detail['estimated_date'] ?? ''),
				'delivery_mode'    => (string)($detail['delivery_mode'] ?? ''),
				'provider_name'    => (string)($detail['provider_name'] ?? ''),
				'service_name'     => (string)($detail['service_name'] ?? ''),
				'carrier_tracking' => (string)($guide['tracking_number'] ?? ''),
				'tracking_id'      => (string)($guide['tracking_id'] ?? ''),
				'tracking_link_provider' => (string)($row['tracking_link'] ?? ''),
				'tracking_link_internal' => (string)($guide['tracking_link'] ?? ''),
				'cancelled_reason' => $reasons ? (string)($reasons['reason']['text'] ?? '') : '',
				'cancelled_at'     => $reasons ? (string)($reasons['date']['cancelled_at'] ?? '') : '',
			];
		}

		return null;
	}

	// ---------- Catalog event listener ----------

	/**
	 * View event for `catalog/view/account/order_info/before` — appends a
	 * "Track shipment" call-to-action to the rendered shipping_method
	 * string when the order shipped via enviatodo.*.
	 *
	 * @param array<string, mixed> $data
	 */
	public function order_info_before(string &$route, array &$data): void {
		if (empty($this->request->get['order_id'])) {
			return;
		}

		$order_id = (int)$this->request->get['order_id'];

		if ($order_id <= 0 || empty($data['shipping_method'])) {
			return;
		}

		// Only inject if the order actually shipped via enviatodo.
		$this->load->model('account/order');
		$order_info = $this->model_account_order->getOrder($order_id);

		if (!$order_info || empty($order_info['shipping_method']) || !is_array($order_info['shipping_method'])) {
			return;
		}

		$code = (string)($order_info['shipping_method']['code'] ?? '');

		if ($code === '' || strpos($code, 'enviatodo.') !== 0) {
			return;
		}

		$this->load->language('extension/enviatodo/shipping/enviatodo');

		$href = $this->url->link(
			'extension/enviatodo/shipping/enviatodo.track',
			'language=' . $this->config->get('config_language')
			. '&customer_token=' . ($this->session->data['customer_token'] ?? '')
			. '&order_id=' . $order_id,
			true
		);

		$label = $this->language->get('button_track_shipment');

		$data['shipping_method'] .= '<br/><a href="' . $href . '" class="btn btn-sm btn-primary mt-2"><i class="fa-solid fa-truck-fast"></i> ' . $label . '</a>';
	}
}
