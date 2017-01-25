<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Predis\Client;
use Psr\SimpleCache\CacheInterface as PSR16CacheInterface;
use QL\MCP\Cache\CacheInterface as MCPCacheInterface;
use QL\MCP\Cache\Utility\CacheInputValidationTrait;
use QL\MCP\Cache\Utility\KeySaltingTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;

/**
 * @internal
 */
class PredisCache implements PSR16CacheInterface, MCPCacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;
    use CacheInputValidationTrait;

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache-' . CacheInterface::VERSION;
    const DELIMITER = ':';
    const GENERATION_KEY = 'mcp-cache-generation-' . CacheInterface::VERSION;

    /**
     * @var client
     */
    private $predis;

    /**
     * @var string|null
     */
    private $suffix;

    /**
     * @var int
     */
    private $cacheGeneration = 1;


    /**
     * An optional suffix can be provided which will be appended to the key
     * used to store and retrieve data. This can be used to throw away cached
     * data with a code push or other configuration change.
     *
     * @param Client $client
     * @param string|null $suffix
     */
    public function __construct(Client $client, $suffix = null)
    {
        $this->predis = $client;
        $this->suffix = $suffix;
        $this->setStoredCacheGeneration();
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
        $key = $this->salted($key, $this->suffix, $this->cacheGeneration);

        $raw = $this->predis->get($key);

        if ($raw === null) {
            return $default;
        }

        return (is_string($raw) ? unserialize($raw) : $raw);
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
    public function set($key, $value, $ttl = null)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix, $this->cacheGeneration);

        /*
         * handle deletions
         *
         * this is not a part of the PSR16 spec but we are going to keep it here to keep
         * backward compatibility with the MCPCacheInterface
         */
        if ($value === null) {
            $this->predis->del($key);

            return true;
        }

        $value = serialize($value);
        // Resolve the TTL to use
        $ttl = $this->determineTTL($ttl);

        // set with expiration
        if ($ttl > 0) {
            $this->predis->setex($key, $ttl, $value);

            return true;
        }

        // set with no expiration
        $this->predis->set($key, $value);

        return true;
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
        $key = $this->salted($key, $this->suffix, $this->cacheGeneration);

        $this->predis->del($key);

        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $this->setCacheGeneration($this->cacheGeneration + 1);

        return true;
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
        //val
        $keys = $this->validateIterable($keys);
        $saltedKeys = $this->validateAndSaltKeys($keys);

        /**
         * 0 indexed array responses but guaranteed to be in the correct order
         */
        $response = $this->predis->mget($saltedKeys);

        //replace null values with passed default
        $replacedValues = array_map(function ($raw) use ($default) {
            if ($raw === null) {
                return $default;
            }

            return (is_string($raw) ? unserialize($raw) : $raw);
        }, $response);

        return array_combine($keys, $replacedValues);
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
        $saltedKeys = $this->validateAndSaltKeys(array_keys($values));

        $serializedValues = array_map("serialize", array_values($values));
        $saltedKeysWithValues = array_combine($saltedKeys, $serializedValues);

        $ttl = $this->determineTTL($ttl);

        //start redis multi call
        $this->predis->multi();

        foreach ($saltedKeysWithValues as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        //resolve multi call
        $this->predis->exec();

        return true;
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
        //validate is iteratable or array and return iterable as an array
        $keys = $this->validateIterable($keys);
        $saltedKeys = $this->validateAndSaltKeys($keys);

        $this->predis->multi();
        foreach ($saltedKeys as $key) {
            $this->predis->del($key);
        }
        $this->predis->exec();

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
        $this->validateKey($key);

        return (bool)$this->predis->exists($key);
    }

    /**
     * Querys the cache for the current cache generation if no cache generation is found than
     * generation 1 is set.
     */
    private function setStoredCacheGeneration()
    {
        $storedGeneration = $this->predis->get(self::GENERATION_KEY);

        if (!$storedGeneration) {
            $storedGeneration = 1;
            $this->setCacheGeneration(1);
        }

        $this->cacheGeneration = $storedGeneration;
    }

    /**
     * Stores the updated cache generation in the cache. This will invalidate all keys
     * passing the generation in their salts
     *
     * @param $cacheGeneration
     */
    private function setCacheGeneration($cacheGeneration)
    {
        $this->predis->set(self::GENERATION_KEY, $cacheGeneration);
        $this->cacheGeneration = $cacheGeneration;
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    private function validateAndSaltKeys(array $keys)
    {
        array_map(['this', 'validateKey'], $keys);

        return array_map(function ($key) {
            return $this->salted($key, $this->suffix, $this->cacheGeneration);
        }, $keys);
    }
}
