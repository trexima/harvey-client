<?php

declare(strict_types=1);

namespace Trexima\HarveyClient;

use GuzzleHttp\BodySummarizer;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
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
    )
    {
        $this->client = new \GuzzleHttp\Client(['base_uri' => rtrim($apiUrl, '/') . '/']);

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
     * @param null $body
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     */
    public function get($resurce, $query = null, $body = null): ResponseInterface
    {
        /**
        $stack = new HandlerStack(Utils::chooseHandler());
        $stack->push(Middleware::httpErrors(new BodySummarizer(2048)), 'http_errors');
        $stack->push(Middleware::redirect(), 'allow_redirects');
        $stack->push(Middleware::cookies(), 'cookies');
        $stack->push(Middleware::prepareBody(), 'prepare_body');
        */
        return $this->client->request('GET', $resurce, [
//            'handler' => $stack,
//            'verify' => false,
            'auth' => [
                $this->apiUsername,
                $this->apiPassword
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json',
                'Accept-language' => $this->language
            ],
            'query' => $query,
            'body' => $body,
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
        $cacheKey = 'isco-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco/' . $code);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * Search ISCO with extended search algorithm
     *
     * @param string|null $title
     * @param int|null $workArea
     * @param string|null $alternativeNames_title
     * @param array $level
     * @param string|null $code
     * @param int|null $revisions
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchIsco(?string $title = null, ?int $workArea = null, ?string $alternativeNames_title = null, array $level = [], ?string $code = null, ?int $revisions = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_filter(array_combine($parameterNames, func_get_args()));
        if (array_key_exists('alternativeNames_title', $args)) {
            // fix due dot requirement in argument
            $args['alternativeNames.title'] = $args['alternativeNames_title'];
            unset($args['alternativeNames_title']);
        }
        if (null === $revisions) {
            $args['revisions'] = $this->getLatestRevision();
        }
        if ($perPage === 0) {
            $args['pagination'] = false;
        }
        $cacheKey = 'search-isco-' . crc32(json_encode($args));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco', $args);
            return (string)$resource->getBody();
        });
        return $this->jsonDecode($result);
    }

    /**
     * Search ISCOS
     *
     * @param array $codes
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchIscos(array $codes = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $query = [
            'code_body' => true,
            'revisions' => $this->getLatestRevision(),
            'perPage' => $perPage,
        ];

        if ($perPage === 0) {
            $query['pagination'] = false;
        }

        $body['code'] = $codes;

        $cacheKey = 'search-iscos-' . crc32(json_encode($query + $body));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $body) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco', $query, json_encode($body));
            return (string)$resource->getBody();
        });
        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $title
     * @param string $order
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function fulltextIsco(?string $title, string $order = 'title'): array
    {
        if (preg_match('/^[0-9]{1,7}$/im', $title)) {
            return $this->searchIsco(null, null, null, [], $title, null, 1, 10000);
        }
        // if it ain't a code, search
        $names = $this->searchIsco($title, null, null, [], null, null, 1, 10000);
        $aNames = $this->searchIsco(null, null, $title, [], null, null, 1, 10000);
        $iscos = array_merge($names, $aNames);
        $iscos = array_intersect_key($iscos, array_unique(array_map('serialize', $iscos)));
        uasort($iscos, function ($a, $b) use ($order) {
            if ($a[$order] === $b[$order]) {
                return 0;
            }
            return ($a[$order] < $b[$order]) ? -1 : 1;
        });
        return $iscos;
    }

    private function getLatestRevision(): int
    {
        $resource = $this->get('/api/revision', ['order[revisionId]' => 'desc', 'perPage' => 1]);
        $response = json_decode((string)$resource->getBody(), true);
        $revision = array_pop($response);
        return (int)$revision['id'];
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
        $cacheKey = 'isco-group-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-group/' . $code);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
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
     * @throws ReflectionException
     */
    public function searchIscoGroup(?string $title = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isco-group-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-group', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'school-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchSchool(?string $title = null, ?string $nuts = null, ?string $street = null, ?string $eduid = null, ?string $kodfak = null, array $type = [], array $root = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school', $args);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param string|null $query
     * @param string|null $nuts Begin of NUTS code
     * @param array $type
     * @param int $year
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchSchoolByYear(?string $query, ?string $nuts = null, array $type = [], ?int $year = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'school-by-year-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-by-year', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'school-legacy-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-legacy/' . $id);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchSchoolLegacy(?string $title = null, ?string $street = null, array $school = [], array $codeLegacy = [], array $year = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-legacy-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-legacy', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'school-type-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-type/' . $code);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchSchoolType($page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-type-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-type', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'school-kov-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov/' . $id);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchSchoolKov($page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-kov-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'school-kov-year-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov-year/' . $id);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchSchoolKovYear(?string $school = null, ?string $kov = null, array $year = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-school-kov-year-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/school-kov-year', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'kov-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchKov(?string $title = null, ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'kov-level-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchKovLevel(?string $title = null, ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-level-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'kov-level-isced-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level-isced/' . $id);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    /**
     * @param array $kovLevel
     * @param int $page
     * @param int $perPage
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws ReflectionException
     */
    public function searchKovLevelIsced(array $kovLevel = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-kov-level-isced-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/kov-level-isced', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'isced-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isced/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchIsced(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isced-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isced', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'sknace-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/sknace/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchSknace(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-sknace-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/sknace', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'cpa-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/cpa/' . $code);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
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
     * @throws ReflectionException
     */
    public function searchCpa(?string $title = null, array $level = [], ?string $code = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-cpa-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/cpa', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'esco-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/esco/' . $id);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
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
     * @throws ReflectionException
     */
    public function searchEsco(?string $title = null, ?string $url = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-esco-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/esco', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'isco-esco-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-esco/' . $id);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
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
     * @throws ReflectionException
     */
    public function searchIscoEsco(array $isco_code = [], array $esco_id = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-isco-esco-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-esco', $args);

            return (string)$resource->getBody();
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
        $cacheKey = 'nuts-' . md5($code);
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($code) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/nuts/' . $code);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchNuts(?string $title = null, array $level = [], $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-nuts-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/nuts', $args);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchOrganization(?string $title = null, ?string $crn = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-organization-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/organization', $args);

            return (string)$resource->getBody();
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
     * @throws ReflectionException
     */
    public function searchPosition(?int $idIstp = null, $page = 1, $perPage = self::RESULTS_PER_PAGE)
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());
        $cacheKey = 'search-position-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/position', $args);

            return (string)$resource->getBody();
        });

        return $this->jsonDecode($result);
    }

    public function getIscoWorkAreas($page = 1, $perPage = self::RESULTS_PER_PAGE, ?string $title = '', $order = [])
    {
        $parameterNames = array_slice($this->methodParameterExtractor->extract(__CLASS__, __FUNCTION__), 0, func_num_args());
        $args = array_combine($parameterNames, func_get_args());

        if ($perPage === 0) {
            $args['pagination'] = false;
        }
        $cacheKey = 'isco-work-area-' . crc32(json_encode([$args]));
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($args) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-work-area', $args);
            return (string)$resource->getBody();
        });
        return $this->jsonDecode($result);
    }

    public function getIscoWorkArea(int $id)
    {
        $cacheKey = 'iscoworkarea-' . $id;
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheTtl);
            $resource = $this->get('api/isco-work-area/' . $id);
            return (string)$resource->getBody();
        });
        return $this->jsonDecode($result);
    }
}
