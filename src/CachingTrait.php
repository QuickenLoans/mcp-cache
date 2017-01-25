<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Psr\SimpleCache\CacheInterface as PSR16CacheInterface;

/**
 * Provide convenience methods and wrappers for caching in repositories.
 *
 * This trait gracefully handles cases where no cache is set.
 *
 * NEVER access properties set in traits directly from within the consumer of the trait!
 *
 * For example:
 * Use $this->cache()
 * Not $this->cache
 */
trait CachingTrait
{
    /**
     * @var PSR16CacheInterface|CacheInterface|null
     */
    private $cache;

    /**
     * @var int|null
     */
    private $cacheTTL;

    /**
     * @return PSR16CacheInterface|CacheInterface|null
     */
    private function cache()
    {
        return $this->cache;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    private function getFromCache($key, $default = null)
    {
        if (!$this->cache()) {
            return null;
        }

        return $this->cache()->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param mixed $ttl
     *
     * @return null
     */
    private function setToCache($key, $value, $ttl = 0)
    {
        if (!$this->cache()) {
            return;
        }

        $params = func_get_args();

        // Use default TTL if none is provided
        if ($this->cacheTTL && func_num_args() < 3) {
            array_push($params, $this->cacheTTL);
        }

        return call_user_func_array([$this->cache(), 'set'], $params);
    }

    /**
     * @param CacheInterface $cache
     *
     * @return null
     */
    public function setCache($cache)
    {
        if (!$cache instanceof CacheInterface && !$cache instanceof PSR16CacheInterface) {
            throw new InvalidArgumentException('You must pass a PSR16 or MCP legacy cache Object');
        }
        $this->cache = $cache;
    }

    /**
     * @param int $ttl
     *
     * @return null
     */
    public function setCacheTTL($ttl)
    {
        $this->cacheTTL = (int) $ttl;
    }

    /**
     * Clears the cache if the cache is not a PSR16CacheInterface it then will do nothing and return silently
     */
    public function clearCache()
    {
        if (!$cache = $this->cache() instanceof PSR16CacheInterface) {
            return;
        }

        $cache->clear();
    }
}
