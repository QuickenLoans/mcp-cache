<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Memcached;
use PHPUnit\Framework\TestCase;
use QL\MCP\Cache\Testing\MemoryLogger;

/**
 * PECL Memcached 2.2.x cannot be tested without errors.
 *
 * @see https://github.com/php-memcached-dev/php-memcached/issues/126
 * @see https://bugs.php.net/bug.php?id=66331
 */
class MemcachedCacheTest extends TestCase
{
    public function setUp()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('pecl-memcached is not installed.');
        }
    }

    public function testDoesntBlowUp()
    {
        $memcached = new Memcached;

        $cache = new MemcachedCache($memcached);

        $this->assertInstanceOf(MemcachedCache::class, $cache);
    }

    public function testNoServersDontCare()
    {
        $memcached = new Memcached;

        $cache = new MemcachedCache($memcached);

        $cache->set('derp', 123);

        $actual = $cache->get('derp');
        $this->assertSame(null, $actual);
    }

    public function testNoServersLogsError()
    {
        $logger = new MemoryLogger;

        $memcached = new Memcached;
        $cache = new MemcachedCache($memcached, 'suffix', $logger);

        $cache->set('derp', 123);

        $actual = $cache->get('derp');

        $expectedError1 = ['error', 'Memcached Error : SET : RES_NO_SERVERS', [
            'cacheKey' => sprintf('mcp-cache-%s:derp:suffix', CacheInterface::VERSION),
            'memcacheError' => 'RES_NO_SERVERS'
        ]];

        $expectedError2 = ['error', 'Memcached Error : GET : RES_NO_SERVERS', [
            'cacheKey' => sprintf('mcp-cache-%s:derp:suffix', CacheInterface::VERSION),
            'memcacheError' => 'RES_NO_SERVERS'
        ]];

        $this->assertCount(2, $logger->messages);
        $this->assertSame($expectedError1, $logger->messages[0]);
        $this->assertSame($expectedError2, $logger->messages[1]);
    }

    public function testLogsWarningOnFailure()
    {
        $logger = new MemoryLogger;

        $memcached = new Memcached;
        $memcached->addServer('example.com', 11211);

        $cache = new MemcachedCache($memcached, null, $logger);

        $cache->set('derp', 123);

        $actual = $cache->get('derp');

        $expectedError1 = ['warning', 'Memcached Error : SET : RES_SERVER_TEMPORARILY_DISABLED', [
            'cacheKey' => sprintf('mcp-cache-%s:derp', CacheInterface::VERSION),
            'memcacheError' => 'RES_SERVER_TEMPORARILY_DISABLED'
        ]];

        $expectedError2 = ['warning', 'Memcached Error : GET : RES_SERVER_TEMPORARILY_DISABLED', [
            'cacheKey' => sprintf('mcp-cache-%s:derp', CacheInterface::VERSION),
            'memcacheError' => 'RES_SERVER_TEMPORARILY_DISABLED'
        ]];

        $this->assertCount(2, $logger->messages);
        $this->assertSame($expectedError1, $logger->messages[0]);
        $this->assertSame($expectedError2, $logger->messages[1]);
    }
}
