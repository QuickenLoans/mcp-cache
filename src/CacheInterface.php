<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

/**
 * @api
 */
interface CacheInterface
{
    const VERSION = '3.0.0';

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function get($key);

    /**
     * Returns true if the data was stored.
     *
     * @param string $key
     * @param mixed $value Anything not a resource
     * @param int $ttl How long the data should live, in seconds
     *
     * @return boolean
     */
    public function set($key, $value, $ttl = 0);
}
