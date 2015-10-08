<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Memcached;

/**
 * @see https://github.com/php-memcached-dev/php-memcached
 * @see https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php
 * @see http://docs.aws.amazon.com/AmazonElastiCache/latest/UserGuide/Appendix.PHPAutoDiscoverySetup.html
 *
 * @internal
 */
class MemcachedCache implements CacheInterface
{
    /**
     * @var Memcached
     */
    private $cache;

    /**
     * @param Memcached $cache
     */
    public function __construct(Memcached $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @see http://docs.php.net/manual/en/memcached.get.php
     *
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->cache->get($key);
    }

    /**
     * @see http://docs.php.net/manual/en/memcached.set.php
     *
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        // handle deletions
        if ($value === null) {
            $this->cache->delete($key);
            return true;
        }

        return $this->cache->set($key, $value, $ttl);
    }
}
