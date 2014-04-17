<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\DataType\Time\TimePoint;

/**
 * @internal
 */
class Memory implements CacheInterface
{
    use ValidationTrait;

    /**
     * @var array
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
        if (!isset($this->cache[$key])) {
            return null;
        }

        return $this->cache[$key];
    }

    /**
     * $ttl is ignored. If your data is living that long in memory, you got issues.
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->validateCacheability($value);

        $this->cache[$key] = $value;
        return true;
    }
}
