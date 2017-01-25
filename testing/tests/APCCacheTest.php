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
use QL\MCP\Common\Time\TimeInterval;

class APCCacheTest extends PHPUnit_Framework_TestCase
{
    const STAMPEDE_RUNS = 1000;

    public $clock;

    public function buildFixedClock($time)
    {
        return new Clock($time, 'UTC');
    }

    public function setUp()
    {
        if (!extension_loaded('apcu')) {
            $this->markTestSkipped('ext-apcu is not installed');
            return;
        }

        if (!ini_get('apc.enabled') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('ext-apcu is not enabled');
            return;
        }

        apcu_clear_cache();

        $this->clock = new Clock('now', 'UTC');
    }

    public function tearDown()
    {
        if (!extension_loaded('apcu')) {
            return;
        }

        apcu_clear_cache();
    }

    public function testSet()
    {
        $cache = new APCCache($this->clock);

        $out = $cache->set('a', 'b');
        $this->assertEquals(true, $out);

        $this->assertEquals('b', $cache->get('a'));
    }

    public function testSetOverwrite()
    {
        $cache = new APCCache($this->clock);

        $out = $cache->set('a', 'b');
        $this->assertEquals(true, $out);

        $out = $cache->set('a', 'c');
        $this->assertEquals(true, $out);

        $this->assertEquals('c', $cache->get('a'));
    }

    public function testGetNotExist()
    {
        $cache = new APCCache($this->clock);

        $this->assertEquals(null, $cache->get('a'));
    }

    public function testGetNotExistReturnsDefault()
    {
        $cache = new APCCache($this->clock);

        $this->assertEquals('default', $cache->get('a', 'default'));
    }

    public function testGetImmediateExpire()
    {
        $cache = new APCCache($this->clock);

        // Set data with long expiry
        $out = $cache->set('a', 'b', 5);
        $this->assertSame('b', $cache->get('a'));

        // Set to null overrides
        $out = $cache->set('a', null);
        $this->assertEquals(true, $out);

        $this->assertEquals(null, $cache->get('a'));
    }

    public function testGetAfterDelete()
    {
        $cache = new APCCache($this->clock);

        // Set data with long expiry
        $out = $cache->set('x', 'y');
        $this->assertSame('y', $cache->get('x'));

        $out = $cache->delete('x');
        $this->assertEquals(true, $out);

        $this->assertEquals(null, $cache->get('x'));
    }

    /**
     * @large
     */
    public function testGetExpired()
    {
        $cache = new APCCache($this->clock);

        $out = $cache->set('a', 'b', new TimeInterval('PT1S'));
        $this->assertEquals(true, $out);

        // sleep until expired
        sleep(2);

        $this->assertEquals(null, $cache->get('a'));
    }

    /**
     * @large
     */
    public function testUseMaxTtl()
    {
        $cache = new APCCache($this->clock);

        $cache->setMaximumTtl(1);
        $out = $cache->set('a', 'b', new \DateInterval('PT1S'));
        $this->assertEquals(true, $out);

        // sleep until expired
        sleep(2);

        $this->assertEquals(null, $cache->get('a'));
    }

    public function testClear()
    {
        $cache = new APCCache($this->clock);
        $cache->set('a', 'b');

        $out = $cache->clear();
        $this->assertEquals(true, $out);

        $this->assertEquals(null, $cache->get('a'));
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
        while ($i++ < self::STAMPEDE_RUNS) {
            if ($cache->get('a') === null) $expired++;
        }

        // using default settings, approx 5% should expire.
        $this->assertGreaterThanOrEqual(self::STAMPEDE_RUNS * .02, $expired);
        $this->assertLessThanOrEqual(self::STAMPEDE_RUNS * .08, $expired);
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
        while ($i++ < self::STAMPEDE_RUNS) {
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

    public function testSetMultipleAndMultiGetAndMultiDeleteReturnsAndHasDefaultForUnsetKeys()
    {
        $defaultValue = 'defaultValue';

        $cacheData = ['foo' => 'storedValue', 'bar' => null];

        $cache = new APCCache($this->clock);

        $cache->setMultiple($cacheData);
        $response = $cache->getMultiple(['foo', 'bar'], $defaultValue);
        $this->assertEquals(['foo' => 'storedValue', 'bar' => $defaultValue], $response);
        $cache->deleteMultiple(['foo', 'bar']);
        $this->assertEquals(['foo' => null, 'bar' => null], $cache->getMultiple(['foo', 'bar']));
    }

    public function testHasReturnsBooleanExpected()
    {
        $cache = new APCCache($this->clock);

        $cache->set('x', 'y', 10);
        $out = $cache->has('x');
        $this->assertTrue($out);
        $this->assertTrue(is_bool($out));
    }

    /**
     * @dataProvider invalidKeyDataProvider
     */
    public function testInvalidArgumentExceptionThrownOnRequiredMethods($method, $args)
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = new APCCache($this->clock);

        $cache->$method(...$args);
    }

    public function invalidKeyDataProvider()
    {
        $invalidKey = '{}()/\\:';

        // returns [$method, []ofMethodArguments]
        return [
            ['get', [$invalidKey]],
            ['set', [$invalidKey, 'foo']],
            ['delete', [$invalidKey]],
            ['getMultiple', [[$invalidKey]]],
            ['setMultiple', [[$invalidKey => 'foo']]],
            ['deleteMultiple', [[$invalidKey]]],
            ['has', [$invalidKey]],
        ];
    }

    /**
     * @dataProvider invalidIterableProvider
     */
    public function testInvalidIterableThrowsException($method, $args)
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = new APCCache($this->clock);

        $cache->$method(...$args);
    }

    public function invalidIterableProvider()
    {
        // returns [$method, []ofMethodArguments]
        return [
            ['getMultiple', ['notAnIterator']],
            ['setMultiple', ['notAnIterator']],
            ['deleteMultiple', ['notAnIterator']],
        ];
    }
}
