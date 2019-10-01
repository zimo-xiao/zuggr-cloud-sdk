<?php

namespace ZuggrCloud;

use Psr\SimpleCache\CacheInterface;
use ZuggrCloud\ZuggrCloudClient;
use ZuggrCloud\Exceptions\ZuggrCloudException;

/**
 * Class ZuggrCloud
 *
 * @package ZuggrCloud
 */
class ZuggrCloud
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $appID;

    /**
     * @var string
     */
    protected $appSecret;

    /**
     * @var string
     */
    protected $appAuthURI;

    /**
     * @var string
     */
    protected $mockDataURI;

    /**
     * @var ZuggrCloudClient
     */
    protected $client;

    /**
     * @var bool
     */
    protected $mock;

    /**
     * @var bool
     */
    protected $useCaChe;

    /**
     * @var simpleDispatcher
     */
    protected $cacheRouteDispatcher;

    /**
     * Instantiates a new ZuggrCloud super-class object.
     *
     * @param CacheInterface $cache
     * @param array $config
     * @param bool $mock
     *
     * @throws ZuggrCloudException
     */
    public function __construct(CacheInterface $cache, array $config, bool $mock = false)
    {
        $this->cache = $cache;

        if (isset($config['app_id'])) {
            $this->appID = $config['app_id'];
        } else {
            throw new ZuggrCloudException('Required "app_id" key not supplied in config');
        }

        if (isset($config['app_secret'])) {
            $this->appSecret = $config['app_secret'];
        } else {
            throw new ZuggrCloudException('Required "app_secret" key not supplied in config');
        }

        if (isset($config['app_auth_uri'])) {
            $this->appAuthURI = $config['app_auth_uri'];
        } else {
            $this->appAuthURI = '/app/oauth/login';
        }

        if (isset($config['mock_data_uri'])) {
            $this->mockDataURI = $config['mock_data_uri'];
        } else {
            $this->mockDataURI = '/test/mock-data';
        }

        if (isset($config['passport_auth_uri'])) {
            $this->passportAuthURI = $config['passport_auth_uri'];
        } else {
            $this->passportAuthURI = '/resource/passport/login';
        }

        if (isset($config['client_config'])) {
            $this->client = new ZuggrCloudClient($config['client_config']);
        } else {
            $this->client = new ZuggrCloudClient();
        }

        if (isset($config['cache'])) {
            if (is_bool($config['cache'])) {
                $this->useCaChe = $config['cache'];
            } else {
                throw new ZuggrCloudException('"cache" key must be type bool');
            }
        } else {
            $this->useCaChe = true;
        }

        if ($this->useCaChe) {
            $cacheRouteList = [
                'rules' => [
                    'admin/{id}' => ['GET', 'PUT', 'DELETE'],
                    'app/{id}' => ['GET', 'PUT', 'DELETE'],
                    'resource/passport/{id}' => ['GET', 'PUT', 'DELETE']
                ],
                'allies' => [
                    'admin/{id}' => [
                        'admin/{id}/credentials' => ['PUT']
                    ],
                    'app/{id}' => [
                        'app/{id}/credentials' => ['PUT']
                    ],
                    'resource/passport/{id}' => [
                        'resource/passport/{id}/credentials' => ['PUT'],
                        'resource/passport/{id}/forget' => ['DELETE']
                    ]
                ]
            ];

            if (isset($config['cache_route_list'])) {
                if (is_array($config['cache_route_list'])) {
                    $this->cacheRouteList = $config['cache_route_list'];
                } else {
                    throw new ZuggrCloudException('"cache_route_list" key must be type array');
                }
            }

            $this->cacheRouteDispatcher = \FastRoute\simpleDispatcher(
                function (\FastRoute\RouteCollector $route) use ($cacheRouteList) {
                    foreach ($cacheRouteList['rules'] as $routeRule => $requests) {
                        $route->addRoute($requests, $routeRule, '#');
                    }

                    foreach ($cacheRouteList['allies'] as $orgRoute => $allies) {
                        foreach ($allies as $routeRule => $requests) {
                            $route->addRoute($requests, $routeRule, str_replace($orgRoute, '', $routeRule));
                        }
                    }
                }
            );
        }

        if ($mock) {
            $this->request();
        }

        $this->mock = $mock;
    }

    /**
     * Makes GET request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param bool $appAuth
     * @param bool $returnRequestOauth
     * @return array
     */
    public function get(
        string $uri,
        array $data = [],
        array $headers = [],
        bool $appAuth = true,
        bool $returnRequestOauth = false
    ): array {
        $uri = Helpers::parseURI($uri, false);

        if ($this->mock) {
            return $this->getMockData('GET', $uri);
        }

        if ($this->useCaChe) {
            $key = md5(__CLASS__ . ':route:'.$uri);
            if ($this->cache->has($key)) {
                return json_decode($this->cache->get($key), true);
            }
        }

        $token = null;

        if ($appAuth && !isset($data['token']) && !isset($headers['Authorization'])) {
            $token = $this->getAppToken();
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        try {
            $out = $this->request('GET', $uri, $data, $headers);
        } catch (\Exception $e) {
            $out = $this->requestAgainAfterTokenRefresh($e, 'GET', $token, $uri, $data, $headers);
        }

        if (isset($out['request_oauth'])) {
            $oauth = $out['request_oauth'];
            if (isset($oauth['access_token']) && isset($oauth['expires_in']) && $appAuth) {
                if ($token != $oauth['access_token']) {
                    $this->storeAuthCache('app', $oauth['access_token'], $oauth['expires_in']);
                }
            }

            if (!$returnRequestOauth) {
                unset($out['request_oauth']);
            }
        }

        if ($this->useCaChe) {
            $this->routeCacheManager('GET', $uri, $out);
        }

        return $out;
    }

    /**
     * Makes POST request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param bool $appAuth
     * @param bool $returnRequestOauth
     * @return array
     */
    public function post(
        string $uri,
        array $data = [],
        array $headers = [],
        bool $appAuth = true,
        bool $returnRequestOauth = false
    ): array {
        $uri = Helpers::parseURI($uri, false);

        if ($this->mock) {
            return $this->getMockData('POST', $uri);
        }

        $token = null;

        if ($appAuth && !isset($data['token']) && !isset($headers['Authorization'])) {
            $token = $this->getAppToken();
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        try {
            $out = $this->request('POST', $uri, $data, $headers);
        } catch (\Exception $e) {
            $out = $this->requestAgainAfterTokenRefresh($e, 'POST', $token, $uri, $data, $headers);
        }

        if (isset($out['request_oauth'])) {
            $oauth = $out['request_oauth'];
            if (isset($oauth['access_token']) && isset($oauth['expires_in']) && $appAuth) {
                if ($token != $oauth['access_token']) {
                    $this->storeAuthCache('app', $oauth['access_token'], $oauth['expires_in']);
                }
            }

            if (!$returnRequestOauth) {
                unset($out['request_oauth']);
            }
        }

        if ($this->useCaChe) {
            $this->routeCacheManager('POST', $uri, $out);
        }

        return $out;
    }

    /**
     * Makes PUT request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param bool $appAuth
     * @param bool $returnRequestOauth
     * @return array
     */
    public function put(
        string $uri,
        array $data = [],
        array $headers = [],
        bool $appAuth = true,
        bool $returnRequestOauth = false
    ): array {
        $uri = Helpers::parseURI($uri, false);

        if ($this->mock) {
            return $this->getMockData('PUT', $uri);
        }

        $token = null;

        if ($appAuth && !isset($data['token']) && !isset($headers['Authorization'])) {
            $token = $this->getAppToken();
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        try {
            $out = $this->request('PUT', $uri, $data, $headers);
        } catch (\Exception $e) {
            $out = $this->requestAgainAfterTokenRefresh($e, 'PUT', $token, $uri, $data, $headers);
        }

        if (isset($out['request_oauth'])) {
            $oauth = $out['request_oauth'];
            if (isset($oauth['access_token']) && isset($oauth['expires_in']) && $appAuth) {
                if ($token != $oauth['access_token']) {
                    $this->storeAuthCache('app', $oauth['access_token'], $oauth['expires_in']);
                }
            }

            if (!$returnRequestOauth) {
                unset($out['request_oauth']);
            }
        }

        if ($this->useCaChe) {
            $this->routeCacheManager('PUT', $uri, $out);
        }

        return $out;
    }

    /**
     * Makes DELETE request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param bool $appAuth
     * @param bool $returnRequestOauth
     * @return array
     */
    public function delete(
        string $uri,
        array $data = [],
        array $headers = [],
        bool $appAuth = true,
        bool $returnRequestOauth = false
    ): array {
        $uri = Helpers::parseURI($uri, false);

        if ($this->mock) {
            return $this->getMockData('DELETE', $uri);
        }

        $token = null;

        if ($appAuth && !isset($data['token']) && !isset($headers['Authorization'])) {
            $token = $this->getAppToken();
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        try {
            $out = $this->request('DELETE', $uri, $data, $headers);
        } catch (\Exception $e) {
            $out = $this->requestAgainAfterTokenRefresh($e, 'DELETE', $token, $uri, $data, $headers);
        }

        if (isset($out['request_oauth'])) {
            $oauth = $out['request_oauth'];
            if (isset($oauth['access_token']) && isset($oauth['expires_in']) && $appAuth) {
                if ($token != $oauth['access_token']) {
                    $this->storeAuthCache('app', $oauth['access_token'], $oauth['expires_in']);
                }
            }

            if (!$returnRequestOauth) {
                unset($out['request_oauth']);
            }
        }

        if ($this->useCaChe) {
            $this->routeCacheManager('DELETE', $uri, $out);
        }

        return $out;
    }

    /** helper functions **/

    /**
     * Get app token from cache, refresh if expired
     *
     * @return string
     */
    private function getAppToken(): string
    {
        $key = md5(__CLASS__ . ':auth:app');

        if (!$out = $this->cache->get($key)) {
            return $this->refreshAppToken();
        }

        return $out;
    }

    /**
     * Refresh app token
     *
     * @return string
     */
    private function refreshAppToken(): string
    {
        $out = $this->request('POST', $this->appAuthURI, [
            'credential_id' => $this->appID,
            'secret' => $this->appSecret
        ]);

        if (!isset($out['access_token']) || !isset($out['expires_in'])) {
            throw new ZuggrCloudException('failed to fetch app token from Zuggr Cloud');
        }

        $this->storeAuthCache('app', $out['access_token'], $out['expires_in']);
            
        return $out['access_token'];
    }

    /**
     * Store token in cache
     *
     * @param string $authType
     * @param string $authID
     * @param string $token
     * @param integer $expiresIn
     * @return void
     */
    private function storeAuthCache(string $authType, string $token, int $expiresIn): void
    {
        $key = md5(__CLASS__ . ':auth:'.$authType);

        $this->cache->set($key, $token, $expiresIn - 60);
    }

    private function routeCacheManager(string $request, string $uri, &$data)
    {
        $routeInfo = $this->cacheRouteDispatcher->dispatch($request, $uri);
        if ($routeInfo[0] == 1) {
            if ($routeInfo[1] != '#') {
                $uri = str_replace($routeInfo[1], '', $uri);
            }

            $key = md5(__CLASS__ . ':route:'.Helpers::parseURI($uri));
            $d = json_encode($data);

            switch ($request) {
                case 'GET':
                    $this->cache->set($key, $d, 10 * 60);
                    break;
                case 'PUT':
                    $this->cache->delete($key);
                    $this->cache->set($key, $d, 10 * 60);
                    break;
                case 'POST':
                    $this->cache->delete($key);
                    $this->cache->set($key, $d, 10 * 60);
                    break;
                case 'DELETE':
                    $this->cache->delete($key);
                    break;
            }
        }
    }

    /**
     * Get mock data from Zuggr Cloud
     *
     * @param string $uri
     * @return array
     */
    private function getMockData(string $method, string $uri): array
    {
        $key = md5(__CLASS__ . ':mock:data');

        if (!$out = $this->cache->get($key)) {
            $out = $this->request('GET', $this->mockDataURI, [
                'credential_id' => $this->appID,
                'secret' => $this->appSecret
            ]);

            if (!isset($out['mock_data'])) {
                throw new ZuggrCloudException('failed to fetch mock data from Zuggr Cloud');
            }

            foreach ($out['mock_data'] as $key => $value) {
                $out['mock_data'][$key] = Helpers::parseURI($value, false);
            }

            $this->cache->set($key, json_encode($out['mock_data']), 86400);

            if (!isset($out['mock_data'][$uri][$method])) {
                throw new ZuggrCloudException('could not find uri ['.$method.'] '.$uri.' in mock data');
            }
            
            return $out['mock_data'][$uri][$method];
        }

        $out = json_decode($out);

        if (!isset($out['mock_data'][$uri][$method])) {
            throw new ZuggrCloudException('could not find uri ['.$method.'] '.$uri.' in mock data');
        }

        return $out[$uri][$method];
    }

    /**
     * Makes the request to Zuggr Cloud and returns the result
     *
     * @param string $method
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param array $files
     * @return array
     */
    private function request(string $method, string $uri, array $data = [], array $headers = []): array
    {
        $requestOptions = [];
        if (strtoupper($method) == 'GET') {
            $requestOptions['query'] = $data;
        } else {
            $requestOptions['form_params'] = $data;
        }

        $response = $this->client->request($method, $uri, array_merge($requestOptions, [
            'headers' => $headers
        ]));

        $out = json_decode($response->getBody(), true);
        
        return is_array($out) ? $out : [];
    }

    private function requestAgainAfterTokenRefresh($e, $method, &$token, &$uri, &$data, &$headers)
    {
        if ($token != null) {
            $code = $e->getResponse()->getStatusCode();
            if (((int)$code) == 422) {
                $this->refreshAppToken();
                return $this->request($method, $uri, $data, $headers);
            } else {
                throw $e;
            }
        } else {
            throw $e;
        }
    }
}
