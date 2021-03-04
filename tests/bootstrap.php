<?php

require_once __DIR__.'/../vendor/autoload.php';

$env = new \Symfony\Component\Dotenv\Dotenv();
$env->load(__DIR__.'/../.env');