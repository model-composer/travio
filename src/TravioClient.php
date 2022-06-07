<?php namespace Model\Travio;

use Firebase\JWT\JWT as FirebaseJWT;
use Model\Config\Config;

class TravioClient
{
	/**
	 * @param string $method
	 * @param string $endpoint
	 * @param array|null $payload
	 * @return array
	 * @throws \Exception
	 */
	public static function request(string $method, string $endpoint, ?array $payload = null): array
	{
		$method = strtoupper($method);

		// TODO: multilingua
//		if (!isset($payload['lang']))
//			$payload['lang'] = $this->model->_Multilang->lang;

		// TODO: debug mode e server staging
//		$url = $this->makeUrl($request, $get);

		$c = curl_init('https://api.travio.it/' . $endpoint);

		$headers = [
			'Connection: close',
		];

		if (!($method === 'POST' and $endpoint === 'auth'))
			$headers[] = 'Authorization: Bearer ' . self::getAuth();

		if ($method === 'POST')
			curl_setopt($c, CURLOPT_POST, 1);
		elseif ($method !== 'GET')
			curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);

		if ($payload !== null) {
			$body = json_encode($payload);
			$headers[] = 'Content-Type: text/json';
			$headers[] = 'Content-length: ' . strlen($body);
			curl_setopt($c, CURLOPT_POSTFIELDS, $body);
		}

		curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

		$data = curl_exec($c);

		if (curl_errno($c))
			throw new \Exception('Errore cURL: ' . curl_error($c));

		$http_code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);

		curl_close($c);

		$decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

		if ($http_code !== 200) {
			if (is_array($decoded)) {
				$error = [];
				if (isset($decoded['error']))
					$error[] = $decoded['error'];
				if (isset($decoded['message']))
					$error[] = $decoded['message'];
				throw new \Exception('Errore in richiesta ' . $endpoint . ': ' . implode(' - ', $error), $http_code);
			} else {
				throw new \Exception('Errore in richiesta ' . $endpoint, $http_code);
			}
		}

		return $decoded;
	}

	/**
	 * Ritorna (richiedendolo se necessario) il token di autorizzazione
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getAuth(): string
	{
		if (isset($_SESSION['travio-auth'])) {
			$decoded = self::decodeTokenIfValid();
			if (!$decoded)
				unset($_SESSION['travio-auth']);
		}

		if (!isset($_SESSION['travio-auth']))
			$_SESSION['travio-auth'] = self::requestAuthToken();

		return $_SESSION['travio-auth'];
	}

	/**
	 * Richiede un nuovo token di autorizzazione
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function requestAuthToken(): string
	{
		$config = self::getConfig();
		$response = self::request('POST', 'auth', $config['auth']);
		if (empty($response['token']))
			throw new \Exception('Auth error');

		return $response['token'];
	}

	/**
	 * Decode token if valid and not expired
	 *
	 * @return array|null
	 */
	private static function decodeTokenIfValid(): ?array
	{
		try {
			$token = explode('.', $_SESSION['travio-auth'] ?? '');
			if (count($token) !== 3)
				throw new \Exception();

			$decoded = json_decode(FirebaseJWT::urlsafeB64Decode($token[1]), true, 512, JSON_THROW_ON_ERROR);
			if (empty($decoded['exp']) or $decoded['exp'] < time())
				throw new \Exception();

			return $decoded;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @throws \Exception
	 */
	public static function login(string $username, string $password): void
	{
		$_SESSION['travio-auth'] = self::request('POST', 'login', [
			'username' => $username,
			'password' => $password,
		]);
	}

	/**
	 */
	public static function logged(): ?array
	{
		return self::decodeTokenIfValid();
	}

	/**
	 */
	public static function logout(): void
	{
		if (isset($_SESSION['travio-auth']))
			unset($_SESSION['travio-auth']);
	}

	/**
	 * Config retriever
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function getConfig(): array
	{
		return Config::get('travio', [
			[
				'version' => '0.1.0',
				'migration' => function (array $currentConfig, string $env) {
					if ($currentConfig) // Already existing
						return $currentConfig;

					if (defined('INCLUDE_PATH') and file_exists(INCLUDE_PATH . 'app/config/Travio/config.php')) {
						// ModEl 3 migration
						require(INCLUDE_PATH . 'app/config/Travio/config.php');

						$config['auth'] = [
							'id' => $config['license'],
							'key' => $config['key'],
						];

						unset($config['license']);
						unset($config['key']);

						$config['target_types'] = $config['target-types'];
						unset($config['target-types']);

						$config['master_data'] = $config['master-data'];
						unset($config['master-data']);

						$config['payment_methods'] = $config['payment-methods'];
						unset($config['payment-methods']);

						$config['payment_conditions'] = $config['payment-conditions'];
						unset($config['payment-conditions']);

						$config['luggage_types'] = $config['luggage-types'];
						unset($config['luggage-types']);

						return $config;
					}

					return [
						'auth' => [
							'id' => null,
							'key' => null,
						],
						'target_types' => [
							[
								'search' => 'service',
								'type' => 2,
							],
						],
						'dev' => true,
						'import' => [
							'geo' => [
								'import' => true,
							],
							'services' => [
								'import' => true,
								'override' => [
									'name' => true,
									'price' => true,
									'classification' => true,
									'classification_level' => true,
									'min_date' => true,
									'max_date' => true,
									'lat' => true,
									'lng' => true,
									'descriptions' => true,
								],
							],
							'subservices' => [
								'import' => false,
							],
							'packages' => [
								'import' => true,
								'override' => [
									'name' => true,
									'price' => true,
								],
							],
							'tags' => [
								'import' => true,
							],
							'amenities' => [
								'import' => true,
							],
							'airports' => [
								'import' => true,
								'override' => [
									'name' => true,
									'departure' => true,
								],
							],
							'ports' => [
								'import' => true,
								'override' => [
									'name' => true,
									'departure' => true,
								],
							],
							'stations' => [
								'import' => true,
								'override' => [
									'name' => true,
								],
							],
							'master_data' => [
								'import' => false,
								'filters' => [],
							],
							'payment_methods' => [
								'import' => true,
								'filters' => [
									'visible_in' => 1,
								],
							],
							'payment_conditions' => [
								'import' => true,
							],
							'luggage_types' => [
								'import' => true,
							],
							'classifications' => [
								'import' => true,
							],
						],
					];
				},
			],
		]);
	}
}
