<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use InvalidArgumentException;

trait ValidationTrait
{
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
