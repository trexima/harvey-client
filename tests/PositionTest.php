<?php

namespace Trexima\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Trexima\HarveyClient\Client;

final class PositionTest extends TestCase
{
    private $harveyClient;
    private $parameterExtractor;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $cache = new ArrayAdapter();
        $this->parameterExtractor = new \Trexima\HarveyClient\MethodParameterExtractor($cache);
        $this->harveyClient = new Client($_ENV['HARVEY_URL'], $_ENV['HARVEY_USERNAME'], $_ENV['HARVEY_PASSWORD'], $this->parameterExtractor, $cache);
    }

    public function testPosition(): void
    {
        $position = $this->harveyClient->searchPosition(5349);
        $this->assertEquals(5349, $position['idIstp']);
        $this->assertEquals('Dispečer, výpravca v železničnej doprave', $position['title']);
    }
}
