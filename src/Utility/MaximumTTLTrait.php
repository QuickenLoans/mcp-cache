<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Utility;

/**
 * Allow a cacher to specify a maximum TTL for cached data.
 *
 * @internal
 */
trait MaximumTTLTrait
{
    /**
     * @var int
     */
    private $maximumTTL = 0;

    /**
     * Set the maximum TTL in seconds.
     *
     * @param int $ttl
     *
     * @return null
     */
    public function setMaximumTtl($ttl)
    {
        $this->maximumTTL = (int) $ttl;
    }

    /**
     * @param int $ttl
     *
     * @return int
     */
    private function determineTtl($ttl)
    {
        // if no max is set, use the user provided value
        if (!$this->maximumTTL) {
            return $ttl;
        }

        // if the provided ttl is over the maximum ttl, use the max.
        if ($this->maximumTTL < $ttl) {
            return $this->maximumTTL;
        }

        // If no ttl is set, use the max
        if ($ttl == 0) {
            return $this->maximumTTL;
        }

        return $ttl;
    }
}
