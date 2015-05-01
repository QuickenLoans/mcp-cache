<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\Cache\Item\Item;

/**
 * @internal
 */
class MemoryCache implements CacheInterface
{
    /**
     * @var Item[]
     */
    private $cache;

    public function __construct()
    {
        $this->cache = [];
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key]->data();
        }

        return null;
    }

    /**
     * $ttl is ignored. If your data is living that long in memory, you got issues.
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->cache[$key] = new Item($value);
        return true;
    }
}
