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
        $cacheKey = 'isco-'.md5($code);
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
    public function searchIsco(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
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
     * Get ISCO group by id
     *
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getIscoGroup(string $code)
    {
        $cacheKey = 'isco-group-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-group/'.$code);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search ISCO group
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
    public function searchIscoGroup(?string $title = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isco-group-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-group', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSchool(string $code)
    {
        $cacheKey = 'school-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school/'.$code);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string|null $nuts Begin of NUTS code
     * @param string|null $street
     * @param string|null $eduid
     * @param string|null $kodfak
     * @param array $type
     * @param array $root Root school code
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSchool(?string $title = null, ?string $nuts = null, ?string $street = null, ?string $eduid = null, ?string $kodfak = null, array $type = [], array $root = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-'.crc32(json_encode([$args]));
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
    public function getSchoolLegacy(int $id)
    {
        $cacheKey = 'school-legacy-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-legacy/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string|null $street
     * @param array $school Array of school codes
     * @param array $codeLegacy
     * @param array $year
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSchoolLegacy(?string $title = null, ?string $street = null, array $school = [], array $codeLegacy = [], array $year = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-legacy-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-legacy', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSchoolType(string $code)
    {
        $cacheKey = 'school-type-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-type/'.$code);

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
    public function searchSchoolType($page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-type-'.crc32(json_encode([$args]));
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
    public function getSchoolKov(int $id)
    {
        $cacheKey = 'school-kov-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov/'.$id);

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
    public function searchSchoolKov($page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-kov-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSchoolKovYear(int $id)
    {
        $cacheKey = 'school-kov-year-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov-year/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $school Code of school
     * @param string $kov Code of KOV
     * @param array $year
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchSchoolKovYear(?string $school = null, ?string $kov = null, array $year = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-kov-year-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov-year', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKov(string $code)
    {
        $cacheKey = 'kov-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov/'.$code);

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
    public function searchKov(?string $title = null, ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKovLevel(string $code)
    {
        $cacheKey = 'kov-level-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level/'.$code);

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
    public function searchKovLevel(?string $title = null, ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-level-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKovLevelIsced(int $id)
    {
        $cacheKey = 'kov-level-isced-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level-isced/'.$id);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param array $kovLevel
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchKovLevelIsced(array $kovLevel = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-level-isced-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level-isced', $args);

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
        $cacheKey = 'isced-'.md5($code);
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
    public function searchIsced(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isced-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isced', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getSknace(string $code)
    {
        $cacheKey = 'sknace-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/sknace/'.$code);

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
    public function searchSknace(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-sknace-'.crc32(json_encode([$args]));
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
        $cacheKey = 'cpa-'.md5($code);
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
    public function searchCpa(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
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

    /**
     * Get ESCO by id
     *
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getEsco(int $id)
    {
        $cacheKey = 'esco-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/esco/'.$id);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search ESCO
     *
     * @param string|null $title
     * @param string|null $url
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchEsco(?string $title = null, ?string $url = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-esco-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/esco', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * Get ISCO -> ESCO by id
     *
     * @param int $id
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getIscoEsco(int $id)
    {
        $cacheKey = 'isco-esco-'.md5($id);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-esco/'.$id);

            return (string) $resource->getBody();
        });

        return  $this->jsonDecode($result);
    }

    /**
     * Search ISCO -> ESCO by ISCO code or ESCO id
     *
     * @param array $isco
     * @param array $esco
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchIscoEsco(array $isco = [], array $esco = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isco-esco-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-esco', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string $code
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getNuts(string $code)
    {
        $cacheKey = 'nuts-'.md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/nuts/'.$code);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param array $level
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchNuts(?string $title = null, array $level = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-nuts-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/nuts', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * Search Organization
     *
     * @param string|null $title
     * @param string|null $crn
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchOrganization(?string $title = null, ?string $crn = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-organization-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/organization', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * Search Position by idIstp
     *
     * @param int|null $idIstp
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function searchPosition(?int $idIstp = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-position-'.crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/position', $args);

            return (string) $resource->getBody();
        });

        return $this->jsonDecode($result);
    }
}
