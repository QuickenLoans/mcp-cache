<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use PHPUnit_Framework_TestCase;
use QL\MCP\Cache\Testing\CachingStub;
use Mockery;

class CachingTraitTest extends PHPUnit_Framework_TestCase
{
    public $cache;

    public function setUp()
    {
        $this->cache = Mockery::mock(CacheInterface::class);
    }

    public function testCacheAccessor()
    {
        $caching = new CachingStub;

        $this->assertNull(null, $caching->cache());

        $caching->setCache($this->cache);
        $this->assertSame($this->cache, $caching->cache());
    }

    public function testSettingToCacheWithoutCacheSetDoesNotBlowUp()
    {
        $caching = new CachingStub;
        $actual = $caching->setToCache('key', 'data');
        $this->assertNull($actual);
    }

    public function testGettingFromCacheWithoutCacheSetDoesNotBlowUp()
    {
        $caching = new CachingStub;
        $actual = $caching->getFromCache('key');
        $this->assertNull($actual);
    }

    public function testSettingToCacheWithCacheSetCallsCache()
    {
        $this->cache
            ->shouldReceive('set')
            ->with('key', 'data')
            ->once();

        $caching = new CachingStub;
        $caching->setCache($this->cache);

        $caching->setToCache('key', 'data');
    }

    public function testSettingCacheToNonMCPLegacyOrPSR16Errors()
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = 'wrong!';

        $caching = new CachingStub();
        $caching->setCache($cache);
    }

    public function testCallingClearCacheWithCacheSetCallsCache()
    {
        $cache = Mockery::mock('Psr\SimpleCache\CacheInterface');
        $cache
            ->shouldReceive('clear')
            ->with()
            ->once();

        $caching = new CachingStub;
        $caching->setCache($cache);

        $caching->clearCache();
    }

    public function testCallingClearCacheWithoutPSR16CacheDoesNotblowUp()
    {
        $cache = Mockery::mock(CacheInterface::class);
        $caching = new CachingStub;
        $caching->setCache($cache);

        $caching->clearCache();
    }


    public function testGettingFromCacheWithCacheSetCallsCache()
    {
        $this->cache
            ->shouldReceive('get')
            ->with('key', null)
            ->once()
            ->andReturn('data2');

        $caching = new CachingStub;
        $caching->setCache($this->cache);

        $actual = $caching->getFromCache('key');
        $this->assertSame('data2', $actual);
    }

    public function testGlobalTTLIsUsedIfNotExplicitlySet()
    {
        $this->cache
            ->shouldReceive('set')
            ->with('key', 'data', 100)
            ->once();

        $caching = new CachingStub;
        $caching->setCache($this->cache);
        $caching->setCacheTTL('100');

        $caching->setToCache('key', 'data');
    }

    public function testGlobalTTLIsIgnoredByLocalTTL()
    {
        $this->cache
            ->shouldReceive('set')
            ->with('key', 'data', 200)
            ->once();

        $caching = new CachingStub;
        $caching->setCache($this->cache);
        $caching->setCacheTTL('100');

        $caching->setToCache('key', 'data', 200);
    }
}
