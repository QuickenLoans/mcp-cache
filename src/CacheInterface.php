<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

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
