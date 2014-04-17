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
    /**
     * @var array
     */
    private $cache = array();

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        return $this->cache[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param TimePoint|null $expiration
     * @return boolean
     */
    public function set($key, $value, TimePoint $expiration = null)
    {
        $this->cache[$key] = $value;
        return true;
    }
}
