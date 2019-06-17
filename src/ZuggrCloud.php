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
     * @param string $authType
     * @param string $authID
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
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('GET', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in']) && $authType) {
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
     * @param string $authType
     * @param string $authID
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
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('POST', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in']) && $authType) {
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
     * @param string $authType
     * @param string $authID
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
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('PUT', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in']) && $authType) {
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
     * @param string $authType
     * @param string $authID
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
            $headers = array_merge($headers, ['Authorization' => 'Bearer '.$token]);
        }

        $out = $this->request('DELETE', $uri, $data, $headers);

        if (isset($out['access_token']) && isset($out['expires_in']) && $authType) {
            if ($token != $out['access_token']) {
                $this->storeAuthCache($authType, $authID, $out['access_token'], $out['expires_in']);
            }
        }

        return $out;
    }


    /**
     * Register token into cache
     *
     * @param string $authType
     * @param array $oauth
     * @param array $info
     * @return void
     */
    public function registerTokenIntoCache(string $authType, array $oauth, array $info): void
    {
        if ($authType != 'user' && $authType != 'admin') {
            throw new ZuggrCloudException('could not register auth type '. $authType . ' into cache');
        }

        if (!isset($info['id'])) {
            throw new ZuggrCloudException('info missing critical information');
        }

        if (!isset($oauth['access_token']) || !isset($oauth['expires_in'])) {
            throw new ZuggrCloudException('oAuth missing critical information');
        }

        $this->storeAuthCache($authType, $info['id'], $oauth['access_token'], $oauth['expires_in']);
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
            throw new ZuggrCloudException('could not find cached token for admin ID '.$adminID);
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
            throw new ZuggrCloudException('could not find cached token for passport ID '.$passportID);
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

        $this->cache->set($key, $token, $expiresIn - 60);
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
}
