<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Utility\CacheInputValidationTrait;
use QL\MCP\Cache\Utility\KeySaltingTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;
use Memcached;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface as PSR16CacheInterface;

/**
 * PHP.NET Docs:
 * @see http://php.net/manual/en/book.memcached.php
 *
 * GitHub Source:
 * @see https://github.com/php-memcached-dev/php-memcached
 * @see https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php
 *
 * ElastiCache Docs:
 * @see http://docs.aws.amazon.com/AmazonElastiCache/latest/UserGuide/Appendix.PHPAutoDiscoverySetup.html
 * @see http://docs.aws.amazon.com/AmazonElastiCache/latest/UserGuide/AutoDiscovery.Using.html
 *
 * @internal
 */
class MemcachedCache implements CacheInterface, PSR16CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;
    use CacheInputValidationTrait;

    const ERR_GET = 'Memcached Error : GET : %s';
    const ERR_SET = 'Memcached Error : SET : %s';
    const ERR_DEL = 'Memcached Error : DEL : %s';
    const ERR_FLUSH = 'Memcached Error : Error deleting all cache keys : %s';

    const UNKNOWN_CODE = '?';

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache-' . CacheInterface::VERSION;
    const DELIMITER = '.';

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
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        $cached = $this->cache->get($key);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return $cached;

        } else if ($code !== Memcached::RES_NOTFOUND) {
            $this->sendAlert(self::ERR_GET, $key, $code);
            return $default;
        }

        return null;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->validateKey($key);
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

        $this->sendAlert(self::ERR_SET, $key, $code);
        return false;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        $this->cache->delete($key);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return true;
        }

        $this->sendAlert(self::ERR_DEL, $key, $code);
        return false;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $this->cache->flush();
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return true;
        }

        $this->sendAlert(self::ERR_FLUSH, '', $code);
        return false;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $keys = $this->validateIterable($keys);

        //validate and salt
        $validAndSaltedKeys = array_map(function($key){
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, $keys);

        //responses only has the keys that did exist in the cache
        $responses = $this->cache->getMulti($validAndSaltedKeys);
        $code = $this->cache->getResultCode();

        $missingKeys = array_diff($validAndSaltedKeys, array_keys($responses));

        $saltedKeysToUnsaltedKeys = array_combine($validAndSaltedKeys, $keys);

        //fill in missing keys with default
        foreach ($missingKeys as $missingKey) {
            $responses[$missingKey] = $default;
        }

        //change salted key responses to un salted responses
        $unsaltedResponse = [];
        foreach ($responses as $saltedKey => $returnValue) {
            $unSaltedKey = $saltedKeysToUnsaltedKeys[$saltedKey];
            $unsaltedResponse[$unSaltedKey] = $returnValue;
        }

        if ($code === Memcached::RES_SUCCESS) {
            return $unsaltedResponse;
        }

        $this->sendAlert(self::ERR_GET, 'Keys: ' . implode(" ", $keys), $code);
        return [];
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $values = $this->validateIterable($values);

        //validate and salt
        $validAndSaltedKeys = array_map(function($key){
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, array_keys($values));

        $values = array_combine($validAndSaltedKeys, array_values($values));

        // Resolve the TTL to use
        $ttl = $this->determineTTL($ttl);

        $this->cache->setMulti($values, $ttl);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return true;
        }

        $this->sendAlert(self::ERR_SET, 'Keys: ' . implode(" ", array_keys($values)), $code);
        return false;

    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $keys = $this->validateIterable($keys);

        //validate and salt
        $validAndSaltedKeys = array_map(function($key){
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, $keys);

        $this->cache->deleteMulti($validAndSaltedKeys);
        $code = $this->cache->getResultCode();

        if ($code === Memcached::RES_SUCCESS) {
            return true;
        }

        $this->sendAlert(self::DELETE_MULTI, 'Keys: ' . implode(" ", $keys), $code);
        return false;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        $this->cache->get($key);
        $resultCode = $this->cache->getResultCode();

        return $resultCode === Memcached::RES_SUCCESS;
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
    private function sendAlert($template, $key, $code)
    {
        if (!$this->logger) {
            return;
        }

        $priority = ($code === Memcached::RES_NO_SERVERS) ? 'error' : 'warning';
        $code = $this->getHumanResultCode($code);

        $msg = sprintf($template, $code);

        $this->logger->$priority($msg, [
            'cacheKey' => $key,
            'memcacheError' => $code
        ]);
    }
}
