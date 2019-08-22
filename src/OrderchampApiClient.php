<?php
namespace Orderchamp\Api;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Orderchamp\Api\Exceptions\OrderchampApiException;

class OrderchampApiClient
{
    public const VERSION = '1.0.0';

    /**
     * @var string
     */
    protected $webUrl = 'https://www.orderchamp.com';

    /**
     * @var string
     */
    protected $apiUrl = 'https://api.orderchamp.com/v1';

    /**
     * @var bool|string
     */
    protected $verify = true;

    /**
     * @var float
     */
    protected $timeout = 10.0;

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $versions = [];

    /**
     * @var string|null
     */
    protected $clientId;

    /**
     * @var string|null
     */
    protected $clientSecret;

    /**
     * @var string|null
     */
    protected $accessToken;

    /**
     * @var string|null
     */
    protected $sharedSecret;

    public function __construct(array $config = [], ClientInterface $client = null)
    {
        $this->configure($config);
        $this->setHttpClient($client ?: new Client);
    }

    public function configure(array $config): self
    {
        $this->setClientId($config['client_id'] ?? null);
        $this->setClientSecret($config['client_secret'] ?? null);
        $this->setAccessToken($config['access_token'] ?? null);
        $this->setSharedSecret($config['shared_secret'] ?? null);
        $this->setWebUrl($config['web_url'] ?? $this->webUrl);
        $this->setApiUrl($config['api_url'] ?? $this->apiUrl);
        $this->setVerify($config['verify'] ?? $this->verify);
        $this->setTimeout($config['timeout'] ?? $this->timeout);

        return $this;
    }

    /**
     * @param ClientInterface $client
     *
     * @return OrderchampApiClient
     */
    public function setHttpClient(ClientInterface $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return OrderchampApiClient
     */
    public function addVersion(string $key, string $value): self
    {
        $this->versions[$key] = "{$key}/{$value}";

        ksort($this->versions);

        return $this;
    }

    /**
     * @param string $webUrl
     *
     * @return OrderchampApiClient
     */
    public function setWebUrl(string $webUrl): self
    {
        $this->webUrl = $webUrl;

        return $this;
    }

    /**
     * @param string $apiUrl
     *
     * @return OrderchampApiClient
     */
    public function setApiUrl(string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    /**
     * @param bool|string $verify
     *
     * @return OrderchampApiClient
     */
    public function setVerify($verify): self
    {
        $this->verify = $verify;

        return $this;
    }

    /**
     * @param float $timeout
     *
     * @return OrderchampApiClient
     */
    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string|null $clientId
     *
     * @return OrderchampApiClient
     */
    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @param string|null $clientSecret
     *
     * @return OrderchampApiClient
     */
    public function setClientSecret(?string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * @param string|null $accessToken
     *
     * @return OrderchampApiClient
     */
    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @param string|null $sharedSecret
     *
     * @return OrderchampApiClient
     */
    public function setSharedSecret(?string $sharedSecret): self
    {
        $this->sharedSecret = $sharedSecret;

        return $this;
    }

    /**
     * @param array       $scopes
     * @param string      $redirectUri
     * @param string|null $state
     *
     * @return string
     */
    public function authorizationUrl(array $scopes, string $redirectUri, string $state = null): string
    {
        $params = [
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(',', $scopes),
            'state'         => $state ?? date('c'),
        ];

        return sprintf('%s/oauth/authorize?%s', $this->webUrl, http_build_query($params));
    }

    /**
     * @param array $params
     *
     * @throws OrderchampApiException
     *
     * @return string
     */
    public function requestToken(array $params): string
    {
        if (!$this->clientId) {
            throw new OrderchampApiException('No client_id was set.');
        }

        if (!$this->clientSecret) {
            throw new OrderchampApiException('No client_secret was set.');
        }

        if (!$this->validateParams($params)) {
            throw new OrderchampApiException('Invalid signature.');
        }

        $url = sprintf('%s/oauth/access_token', $this->webUrl);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => $this->timeout,
                'verify'  => $this->verify,
                'headers' => [
                    'Accept'     => 'application/json',
                    'User-Agent' => $this->getUserAgent(),
                ],
                'json' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $params['code'],
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);
            $response = json_decode($response->getBody(), true);

            return $response['access_token'];
        } catch (GuzzleException $e) {
            throw new OrderchampApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string      $query
     * @param array       $variables
     * @param string|null $operationName
     *
     * @throws OrderchampApiException
     *
     * @return array
     */
    public function graphql(string $query, array $variables = null, string $operationName = null): array
    {
        if (!$this->accessToken) {
            throw new OrderchampApiException('No access_token was set.');
        }

        $url = sprintf('%s/graphql', $this->apiUrl);

        try {
            $response = $this->httpClient->request('POST', $url, [
                //'http_errors' => false,
                'timeout' => $this->timeout,
                'verify'  => $this->verify,
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $this->accessToken),
                    'User-Agent'    => $this->getUserAgent(),
                ],
                'json' => [
                    'query'         => $query,
                    'variables'     => $variables,
                    'operationName' => $operationName,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new OrderchampApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $payload
     * @param string $signature
     *
     * @throws OrderchampApiException
     *
     * @return bool
     */
    public function validateSignature(string $payload, string $signature): bool
    {
        if (!$this->clientSecret && !$this->sharedSecret) {
            throw new OrderchampApiException('No client_secret or shared_secret was set.');
        }

        $calculated = hash_hmac('sha256', $payload, $this->clientSecret ?? $this->sharedSecret);

        return hash_equals($calculated, $signature);
    }

    /**
     * @param array $params
     *
     * @throws OrderchampApiException
     *
     * @return bool
     */
    public function validateParams(array $params): bool
    {
        if (!isset($params['account_id']) || !isset($params['timestamp']) || !isset($params['signature'])) {
            return false;
        }

        $signature = $params['signature'];
        unset($params['signature']);

        ksort($params);

        return $this->validateSignature(http_build_query($params), $signature);
    }

    protected function getUserAgent(): string
    {
        return trim(sprintf('OrderchampApi/%s PHP/%s %s', static::VERSION, PHP_VERSION, implode(' ', $this->versions)));
    }
}
