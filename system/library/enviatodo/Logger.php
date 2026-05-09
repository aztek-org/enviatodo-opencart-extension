<?php
namespace Opencart\System\Library\Enviatodo;
/**
 * Class Logger
 *
 * Persists enviatodo.com API request/response pairs into oc_enviatodo_log
 * honouring the configured log level.
 *
 *  Threshold semantics (matches the admin "log_level" setting):
 *   - off    → write nothing
 *   - error  → write only error rows
 *   - info   → write info + error
 *   - debug  → write everything (debug/info/error)
 *
 * The DB instance is the OpenCart one already wired into the controller
 * scope; we don't open a new connection.
 *
 * @package Opencart\System\Library\Enviatodo
 */
class Logger {
	private const LEVELS = [
		'off'   => 0,
		'error' => 1,
		'info'  => 2,
		'debug' => 3,
	];

	private \Opencart\System\Library\DB $db;
	private int $threshold;
	private string $tablePrefix;
	private string $fileName;

	public function __construct(\Opencart\System\Library\DB $db, string $level = 'error', string $tablePrefix = DB_PREFIX, string $fileName = 'enviatodo.log') {
		$this->db          = $db;
		$this->threshold   = self::LEVELS[$level] ?? self::LEVELS['error'];
		$this->tablePrefix = $tablePrefix;
		$this->fileName    = $fileName;
	}

	public function write(string $level, string $endpoint, ?string $request, ?string $response): void {
		$weight = self::LEVELS[$level] ?? self::LEVELS['info'];

		if ($this->threshold === 0 || $weight > $this->threshold) {
			return;
		}

		$this->db->query("
			INSERT INTO `" . $this->tablePrefix . "enviatodo_log`
				(`level`, `endpoint`, `request`, `response`, `date_added`)
			VALUES (
				'" . $this->db->escape($level) . "',
				'" . $this->db->escape(substr($endpoint, 0, 192)) . "',
				" . ($request === null ? "NULL" : "'" . $this->db->escape($this->truncate($request)) . "'") . ",
				" . ($response === null ? "NULL" : "'" . $this->db->escape($this->truncate((string)$response)) . "'") . ",
				NOW()
			)
		");

		// Mirror to DIR_LOGS so the entry shows up in
		// System > Maintenance > Logs alongside other OpenCart logs.
		if (defined('DIR_LOGS')) {
			$line = sprintf(
				"%s [%s] %s | req=%s | res=%s\n",
				date('Y-m-d H:i:s'),
				strtoupper($level),
				$endpoint,
				$request  !== null ? $this->oneLine($request,  2000) : '-',
				$response !== null ? $this->oneLine($response, 2000) : '-'
			);

			@file_put_contents(DIR_LOGS . $this->fileName, $line, FILE_APPEND);
		}
	}

	private function oneLine(string $payload, int $max): string {
		$payload = preg_replace('/\s+/', ' ', $payload) ?? $payload;

		if (strlen($payload) <= $max) {
			return $payload;
		}

		return substr($payload, 0, $max - 14) . '…[truncated]';
	}

	private function truncate(string $payload, int $max = 65535): string {
		if (strlen($payload) <= $max) {
			return $payload;
		}

		return substr($payload, 0, $max - 14) . '…[truncated]';
	}
}
