<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Memcache;

/**
 * PHP.NET Docs:
 * 
 * @see http://php.net/manual/en/book.memcache.php
 *
 * GitHub Source:
 *
 * @see https://github.com/php/pecl-caching-memcache
 */
class MemcacheCache implements CacheInterface
{
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
    public function get($key)
    {
        return $this->cache->get($key);
    }

    /**
     * @see http://docs.php.net/manual/en/memcache.set.php
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        // handle deletions
        if ($value === null) {
            $this->cache->delete($key);
            return true;
        }

        return $this->cache->set($key, $value, null, $ttl);
    }
}
