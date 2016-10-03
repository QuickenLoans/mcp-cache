<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache\Utility;

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
     * @todo change to "setMaximumTTL"
     *
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
    private function determineTTL($ttl)
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
