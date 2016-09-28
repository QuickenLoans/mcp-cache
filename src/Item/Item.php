<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace MCP\Cache\Item;

use MCP\Cache\Exception;
use QL\MCP\Common\Time\TimePoint;

/**
 * This entity encapsulates expiration for cachers that do not support it natively.
 *
 * @internal
 */
class Item
{
    const ERR_RESOURCE_UNCACHEABLE = 'Resources cannot be cached.';

    /**
     * @var mixed
     */
    private $data;

    /**
     * The absolute point in time the data should expire.
     *
     * @var TimePoint|null
     */
    private $expiry;

    /**
     * The original TTL specified by the client.
     *
     * @var int|null
     */
    private $ttl;

    /**
     * If provided, the data will expire at the $expiry time. If the data is
     * retrieved at the exact moment of expiration, it will not be valid.
     *
     * @param mixed $data
     * @param TimePoint|null $expiry
     * @param int|null $originalTTL
     */
    public function __construct($data, TimePoint $expiry = null, $originalTTL = null)
    {
        $this->validateCacheability($data);

        $this->data = $data;

        $this->expiry = $expiry;
        $this->ttl = $originalTTL;
    }

    /**
     * If provided, the data will be checked for expiration.
     *
     * @param TimePoint|null $now
     *
     * @return mixed
     */
    public function data(TimePoint $now = null)
    {
        if ($now && $this->isExpired($now)) {
            return null;
        }

        return $this->data;
    }

    /**
     * Get the original TTL.
     *
     * @return int|null
     */
    public function ttl()
    {
        return $this->ttl;
    }

    /**
     * @param TimePoint $now
     *
     * @return boolean
     */
    private function isExpired(TimePoint $now)
    {
        if ($this->expiry === null) {
            return false;
        }

        // -1 signifies the now is BEFORE $expiry
        return ($now->compare($this->expiry) !== -1);
    }

    /**
     * Validate if data is cacheable.
     *
     * @param mixed $value
     * @throws InvalidArgumentException
     * @return null
     */
    private function validateCacheability($value)
    {
        if (is_resource($value)) {
            throw new Exception(self::ERR_RESOURCE_UNCACHEABLE);
        }
    }
}
