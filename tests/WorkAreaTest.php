<?php

namespace Trexima\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Trexima\HarveyClient\Client;

final class WorkAreaTest extends TestCase
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

    public function testWorkAreas(): void
    {
        $workAreas = $this->harveyClient->getIscoWorkAreas(1,15);
        $this->assertEquals(15, count($workAreas));
        $workAreas = $this->harveyClient->getIscoWorkAreas(1,1000);
        $this->assertGreaterThan(30, count($workAreas));
        $workArea = $this->harveyClient->getIscoWorkArea(5);
        $this->assertEquals('Poľnohospodárstvo, záhradníctvo, rybolov a veterinárstvo', $workArea['title']);
        $findIscos = $this->harveyClient->searchIsco(null,2,null,[],null, 1, 1, 1000);
    }

    public function testIscoFulltext(): void
    {
        $isco1 = $this->harveyClient->fulltextIsco('Agromechatronik');
        $this->assertEquals(1, count($isco1)); // todo: rewrite, this can change eventually
        $iscoCode = $this->harveyClient->fulltextIsco('7233011');
        $this->assertEquals(1, count($iscoCode)); // there is only 1
    }
}
