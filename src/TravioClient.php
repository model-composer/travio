<?php namespace Model\Travio;

use Firebase\JWT\JWT as FirebaseJWT;
use Model\Config\Config;
use Model\Session\Session;
use Model\Session\SessionInterface;

class TravioClient
{
	private static SessionInterface $session;
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
	 * @return SessionInterface
	 */
	private static function session(): SessionInterface
	{
		if (!isset(self::$session))
			self::$session = new Session();
		return self::$session;
	}

	/**
	 * @param SessionInterface $session
	 */
	public static function setSession(SessionInterface $session): void
	{
		self::$session = $session;
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

		$session = self::session();
		if ($session->has('travio-auth')) {
			$decoded = self::decodeTokenIfValid($session->get('travio-auth'));
			if (!$decoded)
				$session->delete('travio-auth');
		}

		if (!$session->has('travio-auth'))
			$session->set('travio-auth', self::requestAuthToken());

		return $session->get('travio-auth');
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
			if (!empty($decoded['exp']) and $decoded['exp'] < time())
				throw new \Exception();

			return $decoded;
		} catch (\Exception $e) {
			return null;
		}
	}

	public static function clearTokenCache(): void
	{
		$session = self::session();
		if ($session->has('travio-auth'))
			$session->delete('travio-auth');
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

		self::session()->set('travio-auth', $response['token']);

		return $response['token'];
	}

	/**
	 * @param string|null $token
	 * @return array|null
	 */
	public static function logged(?string $token = null): ?array
	{
		$session = self::session();
		if ($token === null)
			$token = $session->has('travio-auth') ? $session->get('travio-auth') : '';

		$decoded = self::decodeTokenIfValid($token);
		if (empty($decoded['user']))
			return null;

		if (!$session->has('travio-user-profile') or $session->get('travio-user-profile')['id'] !== $decoded['user'])
			$session->set('travio-user-profile', self::request('GET', 'profile')['user']);

		return $session->get('travio-user-profile');
	}

	/**
	 */
	public static function logout(): void
	{
		self::$forcedToken = null;
		self::clearTokenCache();
	}

	public static function search(array $payload, ?string $cart = null): array
	{
		$session = self::session();

		$config = Config::get('travio');
		if ($config['share_session_cart'] and !$cart and $session->has('travio-cart'))
			$cart = $session->get('travio-cart');
		if ($cart)
			$payload['cart'] = $cart;

		$response = self::request('POST', 'booking/search', $payload);

		if ($config['share_session_cart'])
			$session->set('travio-cart', $response['cart']);

		return $response;
	}

	public static function results(array $payload): array
	{
		return self::request('POST', 'booking/results', $payload);
	}

	public static function picks(array $payload): array
	{
		return self::request('POST', 'booking/picks', $payload);
	}

	public static function addToCart(array $payload): array
	{
		return self::request('PUT', 'booking/cart', $payload);
	}

	public static function getCart(?string $cart = null, array $options = []): ?array
	{
		if (!$cart) {
			$config = Config::get('travio');
			if ($config['share_session_cart'] and self::session()->has('travio-cart'))
				$cart = self::session()->get('travio-cart');
		}

		if (!$cart)
			return null;

		return self::request('GET', 'booking/cart/' . $cart . '?' . http_build_query($options));
	}

	public static function removeFromCart(string $search_id): array
	{
		return self::request('DELETE', 'booking/cart', ['search_id' => $search_id]);
	}

	public static function place(string $cart_id, array $pax, array $payload = []): array
	{
		$payload['pax'] = $pax;
		return self::request('POST', 'booking/place/' . $cart_id, $payload);
	}

	public static function restList(string $repository, array $query = []): array
	{
		if (isset($query['filters']))
			$query['filters'] = json_encode($query['filters']);
		if (isset($query['sort_by']))
			$query['sort_by'] = json_encode($query['sort_by']);

		$response = self::request('GET', 'rest/' . $repository . '?' . http_build_query($query));
		return $response;
	}

	public static function restGet(string $repository, int $id, array $options = []): array
	{
		if (isset($options['unfold']))
			$options['unfold'] = implode(',', $options['unfold']);

		$response = self::request('GET', 'rest/' . $repository . '/' . $id . '?' . http_build_query($options));
		return $response['data'];
	}

	public static function restCreate(string $repository, array $payload): int
	{
		$response = self::request('POST', 'rest/' . $repository, ['data' => $payload]);
		return $response['id'];
	}

	public static function restUpdate(string $repository, int $id, array $payload): void
	{
		self::request('PUT', 'rest/' . $repository . '/' . $id, ['data' => $payload]);
	}

	public static function restDelete(string $repository, int $id): void
	{
		self::request('DELETE', 'rest/' . $repository . '/' . $id);
	}

	public static function getSignedUrl(string $url): ?string
	{
		try {
			return self::request('POST', 'tools/get-signed-url', ['url' => $url])['url'];
		} catch (\Exception $e) {
			return null;
		}
	}
}
