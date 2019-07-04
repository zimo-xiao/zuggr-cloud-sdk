# Zuggr Cloud SDK for PHP

This repository contains the open source PHP SDK that allows you to access Zuggr Cloud from your PHP app.

## Installation

The Zuggr Cloud PHP SDK can be installed with [Composer](https://getcomposer.org/). Run this command:

```sh
composer require zimo-xiao/zuggr-cloud-sdk
```

## Usage
Simple request to Zuggr Cloud
```php
require_once __DIR__ . '/vendor/autoload.php'; // change path as needed

$config = [
    'app_id' => 'foo',
    'app_secret' => 'bar',
    'client_config' => [
        'node' => 'zcbj'
    ]
];

/**
 * Instantiates a new ZuggrCloud super-class object.
 *
 * @param CacheInterface $cache
 * @param array $config
 * @param bool $mock
 *
 * @throws ZuggrCloudException
 */
$zuggr = new ZuggrCloud\ZuggrCloud($cache, $config, false);

/**
 * Makes request to Zuggr Cloud and returns the result
 *
 * @param string $uri
 * @param bool $appAuth
 * @param array $data
 * @param array $headers
 * @param bool $returnRequestOauth
 * @return array
 */

// for safety reasons, request_oauth will not be returned by default

$zuggr->get('app/oauth/info', true, [], []); // app token auto-magically appears in request header when $appAuth = true

$adminOauth = $zuggr->post('admin/oauth/login', [
    'username' => 'foo',
    'password' => 'bar'
]);

$adminInfo = $zuggr->get('admin/oauth/info', [], [
    'Authorization' => 'Bearer '.$adminOauth['access_token']
]);
```