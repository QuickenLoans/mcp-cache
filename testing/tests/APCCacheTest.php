<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Exception as CacheException;
use QL\MCP\Common\Time\Clock;
use PHPUnit_Framework_TestCase;

class APCCacheTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var APCCache
     */
    private $cache;

    public function buildFixedClock($time)
    {
        return new Clock($time, 'UTC');
    }

    public function setUp()
    {
        if (!function_exists('\apcu_fetch')) {
            $this->markTestSkipped('APC not installed');
            return;
        }

        apcu_clear_cache('user');

        $this->cache = new APCCache(new Clock);
    }

    public function tearDown()
    {
        if (!function_exists('\apcu_fetch')) {
            apcu_clear_cache('user');
        }
    }

    public function testClear()
    {
        $this->cache->set('a', 'b');

        $out = $this->cache->clear();
        $this->assertEquals(true, $out);

        $this->assertEquals(null, $this->cache->get('a'));
    }

    public function testSet()
    {
        $out = $this->cache->set('a', 'b');
        $this->assertEquals(true, $out);

        $this->assertEquals('b', $this->cache->get('a'));
    }

    public function testSetOverwrite()
    {
        $out = $this->cache->set('a', 'b');
        $this->assertEquals(true, $out);

        $out = $this->cache->set('a', 'c');
        $this->assertEquals(true, $out);

        $this->assertEquals('c', $this->cache->get('a'));
    }

    public function testGetNotExist()
    {
        $this->assertEquals(null, $this->cache->get('a'));
    }

    public function testGetImmediateExpire()
    {
        // Set data with long expiry
        $out = $this->cache->set('a', 'b', 5);
        $this->assertSame('b', $this->cache->get('a'));

        // Set to null overrides
        $out = $this->cache->set('a', null);
        $this->assertEquals(true, $out);

        $this->assertEquals(null, $this->cache->get('a'));
    }

    /**
     * @large
     */
    public function testGetExpired()
    {
        $out = $this->cache->set('a', 'b', 1);
        $this->assertEquals(true, $out);

        // sleep until expired
        sleep(2);

        $this->assertEquals(null, $this->cache->get('a'));
    }

    /**
     * @large
     */
    public function testUseMaxTtl()
    {
        $this->cache->setMaximumTtl(1);
        $out = $this->cache->set('a', 'b', 10);
        $this->assertEquals(true, $out);

        // sleep until expired
        sleep(2);

        $this->assertEquals(null, $this->cache->get('a'));
    }

    public function testWithStampedeProtection()
    {
        $clock = $this->buildFixedClock('2015-08-15 12:00:00');
        $cache = new APCCache($clock);
        $cache->set('a', 'b', 60);

        // Reset cache so we can specify a clock time some point in the future
        // 50s (75%) until ttl expires
        $clock = $this->buildFixedClock('2015-08-15 12:00:50');
        $cache = new APCCache($clock);
        $cache->enableStampedeProtection();
        $cache->setPrecomputeBeta(3);
        $cache->setPrecomputeDelta(10);

        $expired = $i = 0;
        while ($i++ < 100) {
            if ($cache->get('a') === null) $expired++;
        }

        // using default settings, approx 5% should expire.
        $this->assertGreaterThanOrEqual(2, $expired);
        $this->assertLessThanOrEqual(8, $expired);
    }

    public function testNoExpiryIgnoresStampedeProtection()
    {
        $clock = $this->buildFixedClock('2015-08-15 12:00:00');
        $cache = new APCCache($clock);
        $cache->set('a', 'b');

        // Reset cache so we can specify a clock time some point in the future
        // 45s (75%) until ttl expires
        $clock = $this->buildFixedClock('2015-08-15 12:00:59');
        $cache = new APCCache($clock);
        $cache->enableStampedeProtection();

        $expired = $i = 0;
        while ($i++ < 100) {
            if ($cache->get('a') === null) $expired++;
        }

        // None should expire
        $this->assertSame(0, $expired);
    }

    public function testBadBetaThrowsException()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Invalid beta specified. An integer between 1 and 10 is required.');

        $cache = new APCCache;
        $cache->setPrecomputeBeta(3.0);
    }

    public function testBadDeltaThrowsException()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Invalid delta specified. An integer between 1 and 100 is required.');

        $cache = new APCCache;
        $cache->setPrecomputeDelta('derp');
    }
}
