<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Utility;

use MCP\Cache\Item\Item;
use MCP\Cache\Exception;
use MCP\DataType\Time\TimePoint;

/**
 * Add stampede protection through probabilistic early expiration.
 *
 * @see https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration
 * @see http://www.vldb.org/pvldb/vol8/p886-vattani.pdf
 *
 * Using default settings, there is a chance of early expiration when 30% or less
 * of the TTL is remaining on a cached value.
 *
 * Example of how this works:
 *
 * beta = 3 (default)
 * delta = 10% of ttl (default)
 *
 * Set a value with a ttl of 60 seconds and run 1000 tests.
 *
 * TTL left   | precentile | early expires   | percent
 * ---------- | ---------- | --------------- | -------
 * 25s        | 60%        |   0 out of 1000 | 0%
 * 20s        | 66%        |  10 out of 1000 | 1%
 * 15s        | 75%        |  40 out of 1000 | 4%
 * 6s         | 90%        | 300 out of 1000 | 30%
 * 3s         | 95%        | 500 out of 1000 | 50%
 *
 * @internal
 */
trait StampedeProtectionTrait
{
    /**
     * @var bool
     */
    private $isStampedeProtectionEnabled = false;

    /**
     * @var int
     */
    private $precomputeBeta = 3;
    private $precomputeDelta = 10;

    /**
     * @return void
     */
    public function enableStampedeProtection()
    {
        $this->isStampedeProtectionEnabled = true;
    }

    /**
     * Set the beta - early expiration scale.
     *
     * This affects the probability of early expiration.
     *
     * Default is 4, Set to any value from 1 to 10 to increase the chance of early expiration.
     *
     * @return void
     */
    public function setPrecomputeBeta($beta)
    {
        if (!is_int($beta) || $beta < 1 || $beta > 10) {
            throw new Exception('Invalid beta specified. An integer between 1 and 10 is required.');
        }

        $this->precomputeBeta = $beta;
    }

    /**
     * Set the delta - percentage of TTL at which data should have a change to expire.
     *
     * Default is 10%, set to higher to increase the time of early expiration.
     *
     * @return void
     */
    public function setPrecomputeDelta($delta)
    {
        if (!is_int($delta) || $delta < 1 || $delta > 100) {
            throw new Exception('Invalid delta specified. An integer between 1 and 100 is required.');
        }

        $this->precomputeDelta = $delta;
    }

    /**
     * @param Item $item
     * @param TimePoint $now
     *
     * @return TimePoint
     */
    private function generatePrecomputeExpiration(Item $item, TimePoint $now)
    {
        // no ttl was stored with cache
        if (!$item->ttl()) {
            return $now;
        }

        if (!$this->isStampedeProtectionEnabled) {
            return $now;
        }

        // .4 is just a random magic number that generates good distribution of beta
        $beta = log1p($this->precomputeBeta * 0.4);
        $delta = $this->precomputeDelta * 0.01 * $item->ttl();
        $r = mt_rand(1, 100) / 100;

        // Generate expiry skew in seconds
        $skew = intval($beta * $delta * log($r));

        return $now->modify(sprintf('-%d seconds', $skew));
    }
}
