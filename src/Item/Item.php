<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Item;

use InvalidArgumentException;
use MCP\DataType\Time\TimePoint;

/**
 * @internal
 */
class Item
{
    /**
     * @var mixed
     */
    private $data;

    /**
     * @var TiemPoint|null
     */
    private $expiry;

    /**
     * If provided, the data will expire at the $expiry time. If the data is
     * retrieved at the exact moment of expiration, it will not be valid.
     *
     * @param mixed $data
     * @param TimePoint|null $expiry
     */
    public function __construct($data, TimePoint $expiry = null)
    {
        $this->validateCacheability($data);

        $this->data = $data;
        $this->expiry = $expiry;
    }

    /**
     * If provided, the data will be checked for expiration.
     *
     * @param TimePoint|null $now
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
     * @param TimePoint $now
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
            throw new InvalidArgumentException('Resources cannot be cached.');
        }
    }
}
