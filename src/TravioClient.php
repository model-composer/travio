<?php namespace Model\Travio;

use Firebase\JWT\JWT as FirebaseJWT;
use Model\Config\Config;

class TravioClient
{
	private static ?string $forcedToken = null;

	/**
	 * Forces a specific token (instead of using the session)
	 *
	 * @param string $token
	 * @return void
	 */
	public static function setAuthToken(string $token): void
	{
		self::$forcedToken = $token;
	}

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
			$headers[] = 'Content-Type: application/json';
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
		if (self::$forcedToken)
			return self::$forcedToken;

		if (isset($_SESSION['travio-auth'])) {
			$decoded = self::decodeTokenIfValid($_SESSION['travio-auth']);
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
		$config = Config::get('travio');
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
	private static function decodeTokenIfValid(string $token): ?array
	{
		try {
			$token = explode('.', $token);
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
	 * @return string
	 * @throws \Exception
	 */
	public static function login(string $username, string $password): string
	{
		$response = self::request('POST', 'login', [
			'username' => $username,
			'password' => $password,
		]);

		$_SESSION['travio-auth'] = $response['token'];

		return $_SESSION['travio-auth'];
	}

	/**
	 * @param string|null $token
	 * @return array|null
	 */
	public static function logged(?string $token = null): ?array
	{
		if ($token === null)
			$token = $_SESSION['travio-auth'] ?? '';

		return self::decodeTokenIfValid($token);
	}

	/**
	 */
	public static function logout(): void
	{
		self::$forcedToken = null;
		if (isset($_SESSION['travio-auth']))
			unset($_SESSION['travio-auth']);
	}

	public static function search(array $payload, ?string $cart = null): array
	{
		$config = Config::get('travio');
		if ($config['share_session_cart'] and !$cart and isset($_SESSION['travio-cart']))
			$cart = $_SESSION['travio-cart'];
		if ($cart)
			$payload['cart'] = $cart;

		$response = self::request('POST', 'booking/search', $payload);

		if ($config['share_session_cart'])
			$_SESSION['travio-cart'] = $response['cart'];

		return $response;
	}

	public static function results(array $payload): array
	{
		return self::request('POST', 'booking/results', $payload);
	}

	public static function pick(array $payload): array
	{
		return self::request('POST', 'booking/picks', $payload);
	}

	public static function getCart(): ?array
	{
		if (!isset($_SESSION['travio-cart']))
			return null;

		return self::request('GET', 'booking/cart/' . $_SESSION['travio-cart']);
	}
}
