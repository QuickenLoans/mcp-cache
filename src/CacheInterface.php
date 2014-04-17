<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\DataType\Time\TimePoint;

/**
 * @api
 */
interface CacheInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param string $key
     * @param string|array|object $value
     * @param TimePoint|null $expiration
     * @return boolean
     */
    public function set($key, $value, TimePoint $expiration = null);
}
