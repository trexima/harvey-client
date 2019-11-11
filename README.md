# Harvey client
Service for making requests on Harvey API v2.

## Installation
Add repository to composer.json:
```
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/trexima/harvey-client.git"
    }
],
```

Installation with Composer:
```
composer require trexima/harvey-client
```

## Example usage
```
<?php

require __DIR__.'/../vendor/autoload.php';

$cache = new Symfony\Component\Cache\Adapter\ArrayAdapter();
$harveyClient = new Trexima\HarveyClient\Client('http://127.0.0.1/', 'test', 'testPwd', $cache);

var_dump($harveyClient->searchIsced());
var_dump($harveyClient->getIsced('35'));
```