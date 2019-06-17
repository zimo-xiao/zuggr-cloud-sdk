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

// app token auto-magically appears in request header when $authType = app

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
$zuggr->get('app/oauth/info', 'app');

// for user & admin token, you need to register them by hand

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
$adminOauth = $zuggr->post('admin/oauth/login', null, null, [
    'username' => 'foo',
    'password' => 'bar'
]);

$adminInfo = $zuggr->get('admin/oauth/info', null, null, [], [
    'Authorization' => 'Bearer '.$oauth['access_token']
]);

/**
 * Register token into cache
 *
 * @param string $authType
 * @param array $oauth
 * @param array $info
 * @return void
 */
$zuggr->registerTokenIntoCache('admin', $adminOauth, $adminInfo);

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
$zuggr->delete('admin/'.$adminInfo['id'], 'admin', $adminInfo['id']); 
```