<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\Cache\Item\Item;
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
    use MaximumTTLTrait;

    const ERR_APC_NOT_INSTALLED = 'APC must be installed to use this cache.';

    /**
     * @var Clock
     */
    private $clock;

    /**
     * @param Clock $clock
     */
    public function __construct(Clock $clock = null)
    {
        if (!function_exists('\apc_fetch')) {
            throw new Exception(self::ERR_APC_NOT_INSTALLED);
        }

        $this->clock = $clock ?: new Clock('now', 'UTC');
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
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
        $expires = null;
        $ttl = $this->determineTtl($ttl);

        // already expired, invalidate stored value and don't insert
        if ($ttl < 0) {
            apc_delete($key);
            return true;
        }

        if ($ttl > 0) {
            $expires = $this->clock->read()->modify(sprintf('+%d seconds', $ttl));
        }

        return apc_store($key, new Item($value, $expires), $ttl);
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
