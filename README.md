# Harvey client
Service for making requests on Harvey API v2.

develop: [![Build Status](https://api.travis-ci.com/trexima/harvey-client.svg?branch=develop)](https://travis-ci.com/trexima/harvey-client)
main: [![Build Status](https://api.travis-ci.com/trexima/harvey-client.svg?branch=main)](https://travis-ci.com/trexima/harvey-client)
## Installation
Installation with Composer:
```
composer require trexima/harvey-client
```

## Example usage
```
<?php

require __DIR__.'/../vendor/autoload.php';

$cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
$methodPatameterExtractor = new \Trexima\HarveyClient\MethodParameterExtractor($cache);

$harveyClient = new Trexima\HarveyClient\Client('http://127.0.0.1/v2/', 'admin', 'my_password', $methodPatameterExtractor, $cache);

var_dump($harveyClient->searchIsced());
var_dump($harveyClient->getIsced('35'));
var_dump($harveyClient->searchIsco('programator', [5], '251'));
var_dump($harveyClient->searchCpa('ryby'));
```