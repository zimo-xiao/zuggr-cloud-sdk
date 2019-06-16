<?php

namespace ZuggrCloud;

use \GuzzleHttp\Client;
use ZuggrCloud\Exceptions\ZuggrCloudClientException;

/**
 * Class ZuggrCloudClient
 *
 * @package ZuggrCloud
 */
class ZuggrCloudClient extends \GuzzleHttp\Client
{
    /**
     * Instantiates a new ZuggrCloudClient object.
     */
    public function __construct(array $config)
    {
        if (!isset($config['node'])) {
            throw new ZuggrCloudClientException('Required "node" key not supplied in config');
        }

        if (!isset($config['timeout'])) {
            $config['timeout'] = 300;
        }

        if (!isset($config['baseURL'])) {
            $config['baseURL'] = 'cloud.zuggr.com';
        }

        if (!isset($config['https'])) {
            $config['https'] = true;
        }

        parent::__construct([
            'timeout' => $config['timeout'],
            'base_uri' => ($config['https'] ? 'https://' : 'http://') . $config['node'] . '.' . $config['baseURL']
        ]);
    }
}