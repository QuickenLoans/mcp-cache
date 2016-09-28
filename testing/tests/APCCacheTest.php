<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace MCP\Cache;

use QL\MCP\Common\Time\Clock;
use PHPUnit_Framework_TestCase;

class APCCacheTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var APCCache
     */
    private $cache;

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
        $cache = new APCCache(new Clock('2015-08-15 12:00:00', 'UTC'));
        $cache->set('a', 'b', 60);

        // Reset cache so we can specify a clock time some point in the future
        // 50s (75%) until ttl expires
        $cache = new APCCache(new Clock('2015-08-15 12:00:50', 'UTC'));
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
        $cache = new APCCache(new Clock('2015-08-15 12:00:00', 'UTC'));
        $cache->set('a', 'b');

        // Reset cache so we can specify a clock time some point in the future
        // 45s (75%) until ttl expires
        $cache = new APCCache(new Clock('2015-08-15 12:00:59', 'UTC'));
        $cache->enableStampedeProtection();

        $expired = $i = 0;
        while ($i++ < 100) {
            if ($cache->get('a') === null) $expired++;
        }

        // None should expire
        $this->assertSame(0, $expired);
    }

    /**
     * @expectedException MCP\Cache\Exception
     * @expectedExceptionMessage Invalid beta specified. An integer between 1 and 10 is required.
     */
    public function testBadBetaThrowsException()
    {
        $cache = new APCCache;
        $cache->setPrecomputeBeta(3.0);
    }

    /**
     * @expectedException MCP\Cache\Exception
     * @expectedExceptionMessage Invalid delta specified. An integer between 1 and 100 is required.
     */
    public function testBadDeltaThrowsException()
    {
        $cache = new APCCache;
        $cache->setPrecomputeDelta('derp');
    }
}
