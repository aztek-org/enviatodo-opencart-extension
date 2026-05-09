<?php
namespace Opencart\System\Library\Enviatodo;
/**
 * Class Client
 *
 * Thin HTTP client for the enviatodo.mx REST API (V2). Handles
 * environment switching, the three required headers
 * (`x-api-key`, `x-enviatodo-app`, `Authorization: Bearer …`),
 * JSON serialisation, error normalisation and request/response
 * logging.
 *
 * Usage:
 *   $client = new Client($token, 'sandbox', $logger);
 *   $resp   = $client->get('Api/get_client_balance');
 *   $resp   = $client->post('Api/rates_client', $payload);
 *
 * Every call returns the decoded JSON body (associative array) on
 * success or throws \RuntimeException on transport / non-2xx /
 * unparseable / API-error responses.
 *
 * @package Opencart\System\Library\Enviatodo
 */
class Client {
	public const BASE_SANDBOX    = 'https://apiqav2.enviatodo.mx/index.php';
	public const BASE_PRODUCTION = 'https://api.enviatodo.com/index.php';

	public const APP_HEADER = 'custom';
	public const API_KEY    = 'enviatodo';

	public const TIMEOUT = 20;

	private string $token;
	private string $baseUrl;
	private ?Logger $logger;

	public function __construct(string $token, string $environment = 'sandbox', ?Logger $logger = null, ?string $baseUrlOverride = null) {
		$this->token   = trim($token);
		$this->baseUrl = $baseUrlOverride !== null && $baseUrlOverride !== ''
			? rtrim($baseUrlOverride, '/')
			: ($environment === 'production' ? self::BASE_PRODUCTION : self::BASE_SANDBOX);
		$this->logger  = $logger;
	}

	public function baseUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * @param array<string, mixed> $query
	 *
	 * @return array<string, mixed>
	 */
	public function get(string $path, array $query = []): array {
		$path = $this->joinPath($path);

		if ($query) {
			$path .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
		}

		return $this->request('GET', $path, null);
	}

	/**
	 * @param array<string, mixed> $body
	 *
	 * @return array<string, mixed>
	 */
	public function post(string $path, array $body): array {
		return $this->request('POST', $this->joinPath($path), $body);
	}

	/**
	 * @param array<string, mixed>|null $body
	 *
	 * @return array<string, mixed>
	 */
	private function request(string $method, string $path, ?array $body): array {
		$url = $this->baseUrl . $path;

		$headers = [
			'Authorization: Bearer ' . $this->token,
			'x-api-key: ' . self::API_KEY,
			'x-enviatodo-app: ' . self::APP_HEADER,
			'Accept: application/json',
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
		curl_setopt($ch, CURLOPT_TIMEOUT,        self::TIMEOUT);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS,      10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$payload = null;

		if ($body !== null) {
			$payload   = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$rawResponse = curl_exec($ch);
		$httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError   = curl_error($ch);
		curl_close($ch);

		$logEndpoint = $method . ' ' . $path;

		if ($rawResponse === false) {
			$this->log('error', $logEndpoint, $payload, '[curl] ' . $curlError);

			throw new \RuntimeException('Enviatodo: HTTP transport error — ' . $curlError);
		}

		$decoded = json_decode((string)$rawResponse, true);

		if (!is_array($decoded)) {
			$this->log('error', $logEndpoint, $payload, (string)$rawResponse);

			throw new \RuntimeException(sprintf(
				'Enviatodo: non-JSON response (HTTP %d) from %s — %s',
				$httpCode,
				$path,
				substr((string)$rawResponse, 0, 200)
			));
		}

		// enviatodo.mx convention varies across endpoints, but errors
		// generally arrive either as a non-2xx HTTP status, or as a 2xx
		// body containing one of: { "status": "error", … },
		// { "error": true, … }, { "meta": "error", … }, { "code": <non-200>, … }.
		$status   = is_string($decoded['status'] ?? null) ? strtolower($decoded['status']) : null;
		$bodyCode = isset($decoded['code']) && is_numeric($decoded['code']) ? (int)$decoded['code'] : null;

		$isError = $httpCode < 200 || $httpCode >= 300
			|| $status === 'error' || $status === 'fail'
			|| ($decoded['meta'] ?? null) === 'error'
			|| ($decoded['error'] ?? null) === true
			|| (is_array($decoded['error'] ?? null) && $decoded['error'] !== [])
			|| ($bodyCode !== null && ($bodyCode < 200 || $bodyCode >= 300));

		$this->log($isError ? 'error' : 'info', $logEndpoint, $payload, (string)$rawResponse);

		if ($isError) {
			$message = $decoded['message']
				?? ($decoded['error']['message'] ?? null)
				?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : null)
				?? ($decoded['msg'] ?? null)
				?? ('HTTP ' . $httpCode);

			if (is_array($message)) {
				$message = json_encode($message, JSON_UNESCAPED_UNICODE);
			}

			throw new \RuntimeException('Enviatodo: ' . $message);
		}

		return $decoded;
	}

	private function joinPath(string $path): string {
		return '/' . ltrim($path, '/');
	}

	private function log(string $level, string $endpoint, ?string $request, ?string $response): void {
		if ($this->logger) {
			$this->logger->write($level, $endpoint, $request, $response);
		}
	}
}
