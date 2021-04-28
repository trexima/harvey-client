<?php

require_once __DIR__.'/../vendor/autoload.php';

$env = new \Symfony\Component\Dotenv\Dotenv();
$env->load(__DIR__.'/../.env');

if (!array_key_exists('HARVEY_URL', $_ENV)) {
    $_ENV['HARVEY_URL'] = getenv('HARVEY_URL');
}
if (!array_key_exists('HARVEY_USERNAME', $_ENV)) {
    $_ENV['HARVEY_USERNAME'] = getenv('HARVEY_USERNAME');
}
if (!array_key_exists('HARVEY_PASSWORD', $_ENV)) {
    $_ENV['HARVEY_PASSWORD'] = getenv('HARVEY_PASSWORD');
}