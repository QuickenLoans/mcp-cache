<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\Cache\Item\Item;
use MCP\Cache\Utility\KeySaltingTrait;
use MCP\Cache\Utility\MaximumTTLTrait;
use MCP\DataType\Time\Clock;

/**
 * APC In-Memory Cache Implementation
 *
 * Since APC handles expired keys poorly, we've enforced TTL values here. For example, regardless of TTL values, APC
 * will not expunge any keys until the cache reaches the maximum size. It will also only invalidate after a call to
 * fetch() which means it will return an expired key once before expunging it.
 */
class APCCache implements CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;

    const ERR_APC_NOT_INSTALLED = 'APC must be installed to use this cache.';

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache';
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
        if (!function_exists('\apc_fetch')) {
            throw new Exception(self::ERR_APC_NOT_INSTALLED);
        }

        $this->clock = $clock ?: new Clock('now', 'UTC');
        $this->suffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $value = apc_fetch($key, $success);

        if ($success && $value instanceof Item) {
            return $value->data($this->clock->read());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = $this->salted($key, $this->suffix);

        $expires = null;
        $ttl = $this->determineTtl($ttl);

        // handle deletions
        if ($value === null) {
            apc_delete($key);
            return true;
        }

        if ($ttl > 0) {
            $expires = $this->clock->read()->modify(sprintf('+%d seconds', $ttl));
        }

        $item = new Item($value, $expires, $ttl);
        return apc_store($key, $item, $ttl);
    }

    /**
     * Clear the cache
     *
     * @return bool
     */
    public function clear()
    {
        return apc_clear_cache('user');
    }
}
