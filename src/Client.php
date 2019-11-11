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
        CacheInterface $cache,
        int $cacheTtl = self::CACHE_TTL,
        string $language = self::DEFAULT_LANGUAGE
    ) {
        $this->client = new \GuzzleHttp\Client(['base_uri' => rtrim($apiUrl, '/').'/']);

        $this->cache = $cache;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->cacheTtl = $cacheTtl;
        $this->language = $language;
    }

    /**
     * Perform get request on API.
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
     * Get ISCO by code.
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
            $resource = $this->get('isco/'.$code);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search ISCO with extended search algorithm.
     *
     * @param null $name
     * @param null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchIsco(?string $name = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'search-isco-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('isco', [
                $args
            ]);

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
            $resource = $this->get('school-type/'.$id);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchSchoolType($page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'school-type-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('school-type', [
                $args
            ]);

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
        $args = func_get_args();
        $cacheKey = 'school-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('school/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $name
     * @param string|null $eduid
     * @param string|null $kodfak
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchSchool(?string $name = null, ?string $eduid = null, ?string $kodfak = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'school-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('school', [
                $args
            ]);

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
        $args = func_get_args();
        $cacheKey = 'kov-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('kov/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $name
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchKov(?string $name = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'kov-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('kov', [
                $args
            ]);

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
        $args = func_get_args();
        $cacheKey = 'kov-school-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('kov-school/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $name
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchKovSchool($page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'kov-school-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('kov-school', [
                $args
            ]);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getIsced(int $id)
    {
        $args = func_get_args();
        $cacheKey = 'isced-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('isced/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $name
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchIsced(?string $name = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'isced-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('isced', [
                $args
            ]);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSknace(int $id)
    {
        $args = func_get_args();
        $cacheKey = 'sknace-'.$id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('sknace/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $name
     * @param string|null $code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function searchSknace(?string $name = null, ?string $code = null, $page = 0, $perPage = self::RESULTS_PER_PAGE)
    {
        $args = func_get_args();
        $cacheKey = 'sknace-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('sknace', [
                $args
            ]);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }
}
