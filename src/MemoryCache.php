<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Item\Item;

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
