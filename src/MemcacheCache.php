<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Memcache;
use Psr\SimpleCache\CacheInterface as PSR16CacheInterface;
use QL\MCP\Cache\Utility\CacheInputValidationTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;

/**
 * PHP.NET Docs:
 * @see http://php.net/manual/en/book.memcache.php
 *
 * GitHub Source:
 * @see https://github.com/php/pecl-caching-memcache
 *
 * @internal
 */
class MemcacheCache implements CacheInterface, PSR16CacheInterface
{
    use CacheInputValidationTrait;
    use MaximumTTLTrait;

    /**
     * @var Memcache
     */
    private $cache;

    /**
     * @param Cache_Memcache $cache
     */
    public function __construct(Memcache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @see http://docs.php.net/manual/en/memcache.get.php
     *
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $this->validateKey($key);

        $cached = $this->cache->get($key);

        return $cached ? $cached : $default;
    }

    /**
     * @see http://docs.php.net/manual/en/memcache.set.php
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->validateKey($key);

        // handle deletions
        if ($value === null) {
            $this->cache->delete($key);

            return true;
        }

        $ttl = $this->determineTTL($ttl);

        return $this->cache->set($key, $value, null, $ttl);
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
        return $this->cache->delete($key);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return $this->cache->flush();
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

        $responses = [];
        foreach ($keys as $key) {
            $cached = $this->get($key, $default);
            $responses[$key] = $cached;
        }

        return $responses;
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

        $responses = [];
        foreach ($values as $key => $value) {
            $responses[] = $this->set($key, $value, $ttl);
        }

        return !in_array(false, $responses);
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

        $responses = [];
        foreach ($keys as $key ) {
            $responses[] = $this->delete($key);
        }

        return true;
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
        $cached = $this->get($key, null);

        return (bool)$cached;
    }
}
