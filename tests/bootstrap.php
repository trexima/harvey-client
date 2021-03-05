<?php

require_once __DIR__.'/../vendor/autoload.php';

$env = new \Symfony\Component\Dotenv\Dotenv();
$env->load(__DIR__.'/../.env');
var_dump($_ENV);
var_dump(getenv('HARVEY_URL'));
if (!array_key_exists('HARVEY_URL', $_ENV)) {
    $_ENV['HARVEY_URL'] = getenv('HARVEY_URL');
}