<?php

declare(strict_types = 1);

namespace Trexima\HarveyClient;

use GuzzleHttp\Exception\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for making requests on Harvey API v2
 */
class Client
{
    private const RESULTS_PER_PAGE = 15,
        CACHE_TTL = 86400, // In seconds (24 hours)
        DEFAULT_LANGUAGE = 'sk_SK';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var MethodParameterExtractor
     */
    private $methodParameterExtractor;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * Number of seconds for cache invalidation.
     *
     * @var int
     */
    private $cacheTtl;

    /**
     * @var string
     */
    private $apiUsername;

    /**
     * @var string
     */
    private $apiPassword;

    /**
     * @var string
     */
    private $language;

    public function __construct(
        string $apiUrl,
        string $apiUsername,
        string $apiPassword,
        MethodParameterExtractor $methodParameterExtractor,
        CacheInterface $cache,
        int $cacheTtl = self::CACHE_TTL,
        string $language = self::DEFAULT_LANGUAGE
    ) {
        $this->client = new \GuzzleHttp\Client(['base_uri' => rtrim($apiUrl, '/').'/']);

        $this->methodParameterExtractor = $methodParameterExtractor;
        $this->cache = $cache;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->cacheTtl = $cacheTtl;
        $this->language = $language;
    }

    /**
     * Perform get request on API
     *
     * @param $resurce
     * @param null $query
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($resurce, $query = null)
    {
        return $this->client->request('GET', $resurce, [
            'auth' => [
                $this->apiUsername,
                $this->apiPassword
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json',
                'Accept-language' => $this->language
            ],
            'query' => $query
        ]);
    }

    /**
     * @param string $json
     * @param bool $assoc
     * @return mixed
     * @throws InvalidArgumentException if the JSON cannot be decoded.
     */
    public function jsonDecode(string $json, $assoc = true)
    {
        return \GuzzleHttp\json_decode($json, $assoc);
    }

    /**
     * Get ISCO by code
     *
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getIsco(string $code)
    {
        $cacheKey = 'isco-'.$code;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco/'.$code);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search ISCO with extended search algorithm
     *
     * @param string|null $title
     * @param array $level
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchIsco(?string $title = null, array $level = [], ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isco-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSchoolType(int $id)
    {
        $cacheKey = 'school-type-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-type/'.$id);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSchoolType($page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'school-type-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-type', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSchool(int $id)
    {
        $cacheKey = 'school-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string|null $eduid
     * @param string|null $kodfak
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSchool(?string $title = null, ?string $eduid = null, ?string $kodfak = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'school-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKov(int $id)
    {
        $cacheKey = 'kov-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchKov(?string $title = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'kov-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKovSchool(int $id)
    {
        $cacheKey = 'kov-school-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-school/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchKovSchool($page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'kov-school-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-school', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getIsced(string $code)
    {
        $cacheKey = 'isced-'.$code;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isced/'.$code);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param array $level
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchIsced(?string $title = null, array $level = [], ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'isced-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isced', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSknace(int $code)
    {
        $cacheKey = 'sknace-'.$code;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/sknace/'.$code);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSknace(?string $title = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'sknace-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/sknace', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }



    /**
     * Get CPA 2015 by code
     *
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getCpa(string $code)
    {
        $cacheKey = 'cpa-'.$code;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/cpa/'.$code);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search CPA 2015
     *
     * @param string|null $title
     * @param string|null $code
     * @param array $level
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchCpa(?string $title = null, array $level = [], ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-cpa-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/cpa', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }
}
