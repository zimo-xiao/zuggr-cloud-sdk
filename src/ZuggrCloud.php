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
    protected $adminAuthURI;

    /**
     * @var string
     */
    protected $passportAuthURI;

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
     * Instantiates a new ZuggrCloud super-class object.
     *
     * @param CacheInterface $cache
     * @param array $config
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
            $this->appAuthURI = '/app/login';
        }

        if (isset($config['admin_auth_uri'])) {
            $this->adminAuthURI = $config['admin_auth_uri'];
        } else {
            $this->adminAuthURI = '/admin/login';
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

        $this->client = isset($config['client_config']) ? new ZuggrCloudClient($config['client_config']) : new ZuggrCloudClient();

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
     * @return array
     */
    public function get(string $uri, string $authType = null, string $authID = null, array $data = [], array $headers = []): array
    {
        if ($this->mock) {
            return $this->getMockData('GET', Helpers::parseURI($uri, false));
        }

        $token = null;

        if ($authType) {
            $token = $this->getTokenByAuth($authType, $authID);
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$token]);
        }

        if ($authType) {
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$this->getTokenByAuth($authType, $authID)]);
        }

        $out = $this->request('GET', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in'])) {
            if ($token != $out['access_token']) {
                $this->storeAuthCache($authType, $authID, $out['access_token'], $out['expires_in']);
            }
        }

        return $out;
    }

    /**
     * Makes POST request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function post(string $uri, string $authType = null, string $authID = null, array $data = [], array $headers = []): array
    {
        if ($this->mock) {
            return $this->getMockData('POST', Helpers::parseURI($uri, false));
        }

        $token = null;

        if ($authType) {
            $token = $this->getTokenByAuth($authType, $authID);
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('POST', $uri, $data, $headers);

        $authType = null;
        switch (Helpers::parseURI($uri)) {
            case Helpers::parseURI($this->adminAuthURI):
                $authType = 'admin';
                break;
            case Helpers::parseURI($this->passportAuthURI):
                $authType = 'admin';
                break;
        }

        if ($authType) {
            if (!isset($out['id']) && !isset($out['access_token']) && !isset($out['expires_in'])) {
                throw new ZuggrCloudException('failed to fetch '.$authType.' token from Zuggr Cloud');
            }
            $this->storeAuthCache($authType, $out['id'], $out['access_token'], $out['expires_in']);
        }

        if (isset($out['access_token']) && isset($out['expires_in'])) {
            if ($token != $out['access_token']) {
                $this->storeAuthCache($authType, $authID, $out['access_token'], $out['expires_in']);
            }
        }

        return $out;
    }

    /**
     * Makes PUT request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function put(string $uri, string $authType = null, string $authID = null, array $data = [], array $headers = []): array
    {
        if ($this->mock) {
            return $this->getMockData('PUT', Helpers::parseURI($uri, false));
        }

        $token = null;

        if ($authType) {
            $token = $this->getTokenByAuth($authType, $authID);
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('PUT', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in'])) {
            if ($token != $out['access_token']) {
                $this->storeAuthCache($authType, $authID, $out['access_token'], $out['expires_in']);
            }
        }

        return $out;
    }

    /**
     * Makes DELETE request to Zuggr Cloud and returns the result
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function delete(string $uri, string $authType = null, string $authID = null, array $data = [], array $headers = []): array
    {
        if ($this->mock) {
            return $this->getMockData('DELETE', Helpers::parseURI($uri, false));
        }

        $token = null;

        if ($authType) {
            $token = $this->getTokenByAuth($authType, $authID);
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$token]);
        }

        if ($authType) {
            $headers = array_merge($authType, ['Authorization' => 'Bearer '.$this->getTokenByAuth($authType, $authID)]);
        }

        $out = $this->request('DELETE', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in'])) {
            if ($token != $out['access_token']) {
                $this->storeAuthCache($authType, $authID, $out['access_token'], $out['expires_in']);
            }
        }

        return $out;
    }

    /** helper functions **/

    /**
     * Get token by auth type
     *
     * @param string $auth
     * @param string $bizID
     * @return string
     */
    private function getTokenByAuth(string $type, string $bizID = null): string
    {
        switch ($type) {
            case 'app':
                return $this->getAppToken();
                break;

            case 'admin':
                return $this->getAdminToken($bizID);
                break;

            case 'passport':
                return $this->getPassportToken($bizID);
                break;
            
            default:
                return null;
        }
    }

    /**
     * Get app token from cache, refresh if expired
     *
     * @return string
     */
    private function getAppToken(): string
    {
        $key = md5(__CLASS__ . ':auth:app:');

        if (!$out = $this->cache->get($key)) {
            $out = $this->request('POST', $this->appAuthURI, [
                'credential_id' => $this->appID,
                'secret' => $this->appSecret
            ]);

            if (!isset($out['access_token']) || !isset($out['expires_in'])) {
                throw new ZuggrCloudException('failed to fetch app token from Zuggr Cloud');
            }

            $this->cache->set($key, $out['access_token'], $out['expires_in'] - 60);
            
            return $out['access_token'];
        }

        return $out;
    }

    /**
     * Get admin token from cache if available
     *
     * @param string $adminID
     * @return string
     */
    private function getAdminToken(string $adminID): string
    {
        $key = md5(__CLASS__ . ':auth:admin:'.$adminID);

        if (!$out = $this->cache->get($key)) {
            throw new ZuggrCloudException('could not find cached token for admin ID '.$adminID. '. To refresh cache, please request URI "'.$this->adminAuthURI.'"');
        }

        return $out;
    }

    /**
     * Get passport token from cache if available
     *
     * @param string $adminID
     * @return string
     */
    private function getPassportToken(string $passportID): string
    {
        $key = md5(__CLASS__ . ':auth:passport:'.$passportID);

        if (!$out = $this->cache->get($key)) {
            throw new ZuggrCloudException('could not find cached token for passport ID '.$passportID. '. To refresh cache, please request URI "'.$this->adminAuthURI.'"');
        }

        return $out;
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
    private function storeAuthCache(string $authType, string $authID, string $token, int $expiresIn): void
    {
        $key = md5(__CLASS__ . ':auth:'.$authType.':'.$authID);

        $this->cache->set($key, $out['access_token'], $expiresIn - 60);
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
            $requestOptions['body'] = $data;
        }

        $response = $this->client->request($method, $uri, array_merge($requestOptions, [
            'headers' => $headers
        ]));

        return json_decode($response->getBody());
    }
}