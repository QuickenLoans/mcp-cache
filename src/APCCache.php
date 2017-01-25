<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Psr\SimpleCache\CacheInterface as PSR16CacheInterface;
use QL\MCP\Cache\Item\Item;
use QL\MCP\Cache\Utility\CacheInputValidationTrait;
use QL\MCP\Cache\Utility\KeySaltingTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;
use QL\MCP\Cache\Utility\StampedeProtectionTrait;
use QL\MCP\Common\Time\Clock;

/**
 * APC In-Memory Cache Implementation
 *
 * Since APC handles expired keys poorly, we've enforced TTL values here. For example, regardless of TTL values, APC
 * will not expunge any keys until the cache reaches the maximum size. It will also only invalidate after a call to
 * fetch() which means it will return an expired key once before expunging it.
 */
class APCCache implements CacheInterface, PSR16CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;
    use StampedeProtectionTrait;
    use CacheInputValidationTrait;

    const ERR_APC_NOT_INSTALLED = 'APC must be installed to use this cache.';

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache-' . CacheInterface::VERSION;
    const DELIMITER = '-';

    /**
     * @var Clock
     */
    private $clock;

    /**
     * An optional suffix can be provided which will be appended to the key
     * used to store and retrieve data. This can be used to throw away cached
     * data with a code push or other configuration change.
     *
     * Alternatively, if multiple applications are deployed to the same server,
     * cached data MUST be namespaced to avoid collision.
     *
     * @param Clock $clock
     * @param string|null $suffix
     */
    public function __construct(Clock $clock = null, $suffix = null)
    {
        if (!extension_loaded('apcu')) {
            throw new Exception(self::ERR_APC_NOT_INSTALLED);
        }

        $this->clock = $clock ?: new Clock('now', 'UTC');
        $this->suffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        $item = apcu_fetch($key, $success);

        if (!$success || !$item instanceof Item) {
            return $default;
        }

        $now = $this->clock->read();
        $earlyExpiry = $this->generatePrecomputeExpiration($item, $now);

        return $item->data($earlyExpiry);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        // handle deletions
        if ($value === null) {
            apcu_delete($key);

            return true;
        }

        // Get exact expiry time, if ttl desired
        $ttl = $this->determineTTL($ttl);
        $expiry = null;
        if ($ttl > 0) {
            $delta = sprintf('+%d seconds', $ttl);
            $expiry = $this->clock->read()->modify($delta);
        }

        $item = new Item($value, $expiry, $ttl);

        return apcu_store($key, $item, $ttl);
    }

    /**
     * Clear the cache
     *
     * @return bool
     */
    public function clear()
    {
        return apcu_clear_cache();
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

        apcu_delete($key);

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
        $keys = $this->validateIterable($keys);
        $saltedKeys = array_map(function($key){
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, $keys);

        $responses = [];
        for ($i = 0; $i < count($keys); $i++) {
            $responseKey = $keys[$i];
            $responseValue = $this->get($saltedKeys[$i], $default);

            $responses[$responseKey] = $responseValue;
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
        $saltedKeys = array_map(function ($key) {
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, array_keys($values));

        $saltedKeysAndValues = array_combine($saltedKeys, array_values($values));

        foreach ($saltedKeysAndValues as $key => $value) {
            $this->set($key, $value, $ttl);
        }

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
        $keys = $this->validateIterable($keys);
        $saltedKeys = array_map(function($key){
            $this->validateKey($key);
            return $this->salted($key, $this->suffix);
        }, $keys);

        foreach ($saltedKeys as $key) {
            $this->delete($key);
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
        $this->validateKey($key);
        $key = $this->salted($key, $this->suffix);

        return apcu_exists($key);
    }
}
