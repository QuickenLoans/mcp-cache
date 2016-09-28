<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Item\Item;
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
class APCCache implements CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;
    use StampedeProtectionTrait;

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
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $item = apcu_fetch($key, $success);

        if (!$success || !$item instanceof Item) {
            return null;
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
     * WARNING: Nonstandard method.
     *
     * @return bool
     */
    public function clear()
    {
        return apcu_clear_cache();
    }
}
