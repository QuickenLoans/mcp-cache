<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Memcached;
use Psr\Log\LoggerInterface;
use QL\MCP\Cache\Utility\KeySaltingTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;

/**
 * PHP.NET Docs:
 *
 * @see http://php.net/manual/en/book.memcached.php
 *
 * GitHub Source:
 *
 * @see https://github.com/php-memcached-dev/php-memcached
 * @see https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php
 *
 * ElastiCache Docs:
 *
 * @see http://docs.aws.amazon.com/AmazonElastiCache/latest/UserGuide/Appendix.PHPAutoDiscoverySetup.html
 * @see http://docs.aws.amazon.com/AmazonElastiCache/latest/UserGuide/AutoDiscovery.Using.html
 */
class MemcachedCache implements CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;

    const ERR_GET = 'Memcached Error : GET : %s';
    const ERR_SET = 'Memcached Error : SET : %s';

    const UNKNOWN_CODE = '?';

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache-' . CacheInterface::VERSION;
    const DELIMITER = ':';

    /**
     * @var Memcached
     */
    private $cache;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var string|null
     */
    private $suffix;

    /**
     * To generate list:
     *
     * ```php
     * $results = [];
     * array_walk((new ReflectionClass('Memcached'))->getConstants(), function($v, $k) use (&$results) {
     *     if (substr($k, 0, 4) === 'RES_') $results[$k] = $v;
     * });
     * var_dump($results);
     * ```
     *
     * @var array
     */
    private $resultCodes = [
        'RES_SUCCESS' => 0,
        'RES_FAILURE' => 1,
        'RES_HOST_LOOKUP_FAILURE' => 2,
        'RES_UNKNOWN_READ_FAILURE' => 7,
        'RES_PROTOCOL_ERROR' => 8,
        'RES_CLIENT_ERROR' => 9,
        'RES_SERVER_ERROR' => 10,
        'RES_WRITE_FAILURE' => 5,
        'RES_DATA_EXISTS' => 12,
        'RES_NOTSTORED' => 14,
        'RES_NOTFOUND' => 16,
        'RES_PARTIAL_READ' => 18,
        'RES_SOME_ERRORS' => 19,
        'RES_NO_SERVERS' => 20,
        'RES_END' => 21,
        'RES_ERRNO' => 26,
        'RES_BUFFERED' => 32,
        'RES_TIMEOUT' => 31,
        'RES_BAD_KEY_PROVIDED' => 33,
        'RES_STORED' => 15,
        'RES_DELETED' => 22,
        'RES_STAT' => 24,
        'RES_ITEM' => 25,
        'RES_NOT_SUPPORTED' => 28,
        'RES_FETCH_NOTFINISHED' => 30,
        'RES_SERVER_MARKED_DEAD' => 35,
        'RES_UNKNOWN_STAT_KEY' => 36,
        'RES_INVALID_HOST_PROTOCOL' => 34,
        'RES_MEMORY_ALLOCATION_FAILURE' => 17,
        'RES_CONNECTION_SOCKET_CREATE_FAILURE' => 11,
        'RES_E2BIG' => 37,
        'RES_KEY_TOO_BIG' => 39,
        'RES_SERVER_TEMPORARILY_DISABLED' => 47,
        'RES_SERVER_MEMORY_ALLOCATION_FAILURE' => 48,
        'RES_AUTH_PROBLEM' => 40,
        'RES_AUTH_FAILURE' => 41,
        'RES_AUTH_CONTINUE' => 42,
        'RES_PAYLOAD_FAILURE' => -1001
    ];

    /**
     * An optional suffix can be provided which will be appended to the key
     * used to store and retrieve data. This can be used to throw away cached
     * data with a code push or other configuration change.
     *
     * Optionally, set a logger to enable logging of non-successful memcached responses.
     * - Error, If no servers in memcached pool
     * - Warning, all other result codes
     *
     * @param Memcached $cache
     * @param string|null $suffix
     * @param LoggerInterface|null $logger
     */
    public function __construct(Memcached $cache, $suffix = null, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->suffix = $suffix;
        $this->logger = $logger;

        // Flip the result codes dict for efficiency.
        $this->resultCodes = array_flip($this->resultCodes);
    }

    /**
     * @see http://docs.php.net/manual/en/memcached.get.php
     *
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $cached = $this->cache->get($key);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return $cached;

        } elseif ($code !== Memcached::RES_NOTFOUND) {
            $this->sendAlert('get', $key, $code);
        }

        return null;
    }

    /**
     * @see http://docs.php.net/manual/en/memcached.set.php
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = $this->salted($key, $this->suffix);

        // handle deletions
        if ($value === null) {
            $this->cache->delete($key);
            return true;
        }

        // Resolve the TTL to use
        $ttl = $this->determineTTL($ttl);

        $this->cache->set($key, $value, $ttl);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return true;
        }

        $this->sendAlert('set', $key, $code);
        return false;
    }

    /**
     * @param int $code
     *
     * @return string
     */
    private function getHumanResultCode($code)
    {
        if (isset($this->resultCodes[$code])) {
            return $this->resultCodes[$code];
        }

        if (is_scalar($code)) {
            return $code;
        }

        return self::UNKNOWN_CODE;
    }

    /**
     * @param string $type
     * @param string $key
     * @param string $code
     *
     * @return void
     */
    private function sendAlert($type, $key, $code)
    {
        if (!$this->logger) {
            return;
        }

        $template = ($type === 'get') ? self::ERR_GET : self::ERR_SET;
        $priority = ($code === Memcached::RES_NO_SERVERS) ? 'error' : 'warning';
        $code = $this->getHumanResultCode($code);

        $msg = sprintf($template, $code);

        $this->logger->$priority($msg, [
            'cacheKey' => $key,
            'memcacheError' => $code,
        ]);
    }
}
