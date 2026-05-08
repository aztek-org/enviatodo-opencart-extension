<?php
namespace Opencart\Admin\Model\Extension\Enviatodo\Shipping;
/**
 * Class Enviatodo
 *
 * Schema management + admin queries for the enviatodo shipping extension.
 *
 * @package Opencart\Admin\Model\Extension\Enviatodo\Shipping
 */
class Enviatodo extends \Opencart\System\Engine\Model {
	public function install(): void {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enviatodo_origin` (
				`origin_id` INT(11) NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(96) NOT NULL,
				`contact` VARCHAR(96) NOT NULL DEFAULT '',
				`phone` VARCHAR(32) NOT NULL DEFAULT '',
				`email` VARCHAR(96) NOT NULL DEFAULT '',
				`street` VARCHAR(128) NOT NULL DEFAULT '',
				`number` VARCHAR(32) NOT NULL DEFAULT '',
				`district` VARCHAR(96) NOT NULL DEFAULT '',
				`city` VARCHAR(96) NOT NULL DEFAULT '',
				`state` VARCHAR(96) NOT NULL DEFAULT '',
				`postal_code` VARCHAR(16) NOT NULL DEFAULT '',
				`country` VARCHAR(8) NOT NULL DEFAULT 'MX',
				`is_default` TINYINT(1) NOT NULL DEFAULT 0,
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`origin_id`),
				KEY `is_default` (`is_default`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enviatodo_shipment` (
				`shipment_id` INT(11) NOT NULL AUTO_INCREMENT,
				`order_id` INT(11) NOT NULL,
				`origin_id` INT(11) NOT NULL DEFAULT 0,
				`carrier` VARCHAR(48) NOT NULL DEFAULT '',
				`service` VARCHAR(96) NOT NULL DEFAULT '',
				`tracking_number` VARCHAR(96) NOT NULL DEFAULT '',
				`label_url` VARCHAR(512) NOT NULL DEFAULT '',
				`status` VARCHAR(32) NOT NULL DEFAULT 'pending',
				`request_json` LONGTEXT NULL,
				`response_json` LONGTEXT NULL,
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`shipment_id`),
				KEY `order_id` (`order_id`),
				KEY `tracking_number` (`tracking_number`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enviatodo_carrier_cache` (
				`code` VARCHAR(48) NOT NULL,
				`name` VARCHAR(128) NOT NULL DEFAULT '',
				`services_json` LONGTEXT NULL,
				`refreshed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enviatodo_log` (
				`log_id` INT(11) NOT NULL AUTO_INCREMENT,
				`level` VARCHAR(16) NOT NULL DEFAULT 'info',
				`endpoint` VARCHAR(192) NOT NULL DEFAULT '',
				`request` MEDIUMTEXT NULL,
				`response` MEDIUMTEXT NULL,
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`log_id`),
				KEY `level` (`level`),
				KEY `date_added` (`date_added`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");
	}

	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "enviatodo_origin`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "enviatodo_shipment`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "enviatodo_carrier_cache`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "enviatodo_log`");
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getOrigins(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` ORDER BY `is_default` DESC, `name` ASC");

		return $query->rows;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getOrigin(int $origin_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_origin` WHERE `origin_id` = " . (int)$origin_id);

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function addOrigin(array $data): int {
		$is_default = !empty($data['is_default']) ? 1 : 0;

		if ($is_default) {
			$this->db->query("UPDATE `" . DB_PREFIX . "enviatodo_origin` SET `is_default` = 0");
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "enviatodo_origin` SET
			`name`        = '" . $this->db->escape((string)($data['name']        ?? '')) . "',
			`contact`     = '" . $this->db->escape((string)($data['contact']     ?? '')) . "',
			`phone`       = '" . $this->db->escape((string)($data['phone']       ?? '')) . "',
			`email`       = '" . $this->db->escape((string)($data['email']       ?? '')) . "',
			`street`      = '" . $this->db->escape((string)($data['street']      ?? '')) . "',
			`number`      = '" . $this->db->escape((string)($data['number']      ?? '')) . "',
			`district`    = '" . $this->db->escape((string)($data['district']    ?? '')) . "',
			`city`        = '" . $this->db->escape((string)($data['city']        ?? '')) . "',
			`state`       = '" . $this->db->escape((string)($data['state']       ?? '')) . "',
			`postal_code` = '" . $this->db->escape((string)($data['postal_code'] ?? '')) . "',
			`country`     = '" . $this->db->escape((string)($data['country']     ?? 'MX')) . "',
			`is_default`  = " . $is_default
		);

		return (int)$this->db->getLastId();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function editOrigin(int $origin_id, array $data): void {
		$is_default = !empty($data['is_default']) ? 1 : 0;

		if ($is_default) {
			$this->db->query("UPDATE `" . DB_PREFIX . "enviatodo_origin` SET `is_default` = 0 WHERE `origin_id` <> " . (int)$origin_id);
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "enviatodo_origin` SET
			`name`        = '" . $this->db->escape((string)($data['name']        ?? '')) . "',
			`contact`     = '" . $this->db->escape((string)($data['contact']     ?? '')) . "',
			`phone`       = '" . $this->db->escape((string)($data['phone']       ?? '')) . "',
			`email`       = '" . $this->db->escape((string)($data['email']       ?? '')) . "',
			`street`      = '" . $this->db->escape((string)($data['street']      ?? '')) . "',
			`number`      = '" . $this->db->escape((string)($data['number']      ?? '')) . "',
			`district`    = '" . $this->db->escape((string)($data['district']    ?? '')) . "',
			`city`        = '" . $this->db->escape((string)($data['city']        ?? '')) . "',
			`state`       = '" . $this->db->escape((string)($data['state']       ?? '')) . "',
			`postal_code` = '" . $this->db->escape((string)($data['postal_code'] ?? '')) . "',
			`country`     = '" . $this->db->escape((string)($data['country']     ?? 'MX')) . "',
			`is_default`  = " . $is_default . "
			WHERE `origin_id` = " . (int)$origin_id
		);
	}

	public function deleteOrigin(int $origin_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "enviatodo_origin` WHERE `origin_id` = " . (int)$origin_id);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getCarriers(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_carrier_cache` ORDER BY `name` ASC");

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	public function upsertCarrier(string $code, array $row): void {
		$this->db->query("REPLACE INTO `" . DB_PREFIX . "enviatodo_carrier_cache` SET
			`code`          = '" . $this->db->escape($code) . "',
			`name`          = '" . $this->db->escape((string)($row['name']          ?? '')) . "',
			`services_json` = '" . $this->db->escape((string)($row['services_json'] ?? ''))  . "',
			`refreshed_at`  = NOW()"
		);
	}

	public function clearCarriers(): void {
		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "enviatodo_carrier_cache`");
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function addShipment(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "enviatodo_shipment` SET
			`order_id`        = " . (int)($data['order_id'] ?? 0) . ",
			`origin_id`       = " . (int)($data['origin_id'] ?? 0) . ",
			`carrier`         = '" . $this->db->escape((string)($data['carrier']         ?? '')) . "',
			`service`         = '" . $this->db->escape((string)($data['service']         ?? '')) . "',
			`tracking_number` = '" . $this->db->escape((string)($data['tracking_number'] ?? '')) . "',
			`label_url`       = '" . $this->db->escape((string)($data['label_url']       ?? '')) . "',
			`status`          = '" . $this->db->escape((string)($data['status']          ?? 'pending')) . "',
			`request_json`    = '" . $this->db->escape((string)($data['request_json']    ?? '')) . "',
			`response_json`   = '" . $this->db->escape((string)($data['response_json']   ?? '')) . "'"
		);

		return (int)$this->db->getLastId();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function editShipment(int $shipment_id, array $data): void {
		$set = [];

		$fields = ['carrier', 'service', 'tracking_number', 'label_url', 'status', 'request_json', 'response_json'];

		foreach ($fields as $f) {
			if (array_key_exists($f, $data)) {
				$set[] = "`" . $f . "` = '" . $this->db->escape((string)$data[$f]) . "'";
			}
		}

		if (array_key_exists('origin_id', $data)) {
			$set[] = "`origin_id` = " . (int)$data['origin_id'];
		}

		if (!$set) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "enviatodo_shipment` SET " . implode(', ', $set) . " WHERE `shipment_id` = " . (int)$shipment_id);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getShipment(int $shipment_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_shipment` WHERE `shipment_id` = " . (int)$shipment_id);

		return $query->num_rows ? (array)$query->row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getShipmentByOrderId(int $order_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_shipment` WHERE `order_id` = " . (int)$order_id . " ORDER BY `shipment_id` DESC LIMIT 1");

		return $query->num_rows ? (array)$query->row : null;
	}

	public function getLogs(int $limit = 50, ?string $level = null): array {
		$where = '';

		if ($level !== null && $level !== '') {
			$where = " WHERE `level` = '" . $this->db->escape($level) . "'";
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "enviatodo_log`" . $where . " ORDER BY `log_id` DESC LIMIT " . (int)$limit);

		return $query->rows;
	}
}
