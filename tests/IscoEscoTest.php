<?php

namespace Trexima\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Trexima\HarveyClient\Client;

final class IscoEscoTest extends TestCase
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

    public function testIscoEsco(): void
    {
        $iscoEsco = $this->harveyClient->searchIscoEsco(['1114004']);
        $this->assertGreaterThan(0, count($iscoEsco));
    }
}
