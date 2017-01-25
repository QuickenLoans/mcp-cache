<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Memcache;
use Mockery;
use PHPUnit_Framework_TestCase;

class MemcacheCacheTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('pecl-memcache is not installed');
        }
    }

    public function testSettingAKeyWithoutExpirationUsesZeroAsDefault()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('set')
            ->with('mykey', 'testvalue', null, 0)
            ->once();

        $cache = new MemcacheCache($memcache);
        $cache->set('mykey', 'testvalue');
    }

    public function testGettingKey()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('get')
            ->with('mykey')
            ->andReturn('testvalue2')
            ->once();

        $cache = new MemcacheCache($memcache);
        $actual = $cache->get('mykey');

        $this->assertSame('testvalue2', $actual);
    }

    public function testGettingKeyDefault()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('get')
            ->with('mykey')
            ->andReturn(null)
            ->once();

        $cache = new MemcacheCache($memcache);
        $actual = $cache->get('mykey', 'testvalue2');

        $this->assertSame('testvalue2', $actual);
    }

    public function testSettingWithExpiration()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('set')
            ->with('mykey', 'testvalue', null, 600)
            ->once();

        $cache = new MemcacheCache($memcache);
        $cache->set('mykey', 'testvalue', 600);
    }

    public function testDeleteIsUsedIfValueIsNull()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('delete')
            ->with('mykey')
            ->once();

        $cache = new MemcacheCache($memcache);
        $cache->set('mykey', null, 600);
    }

    public function testDeleteCallsWithKey()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('delete')
            ->with('mykey')
            ->once();

        $cache = new MemcacheCache($memcache);
        $cache->delete('mykey');
    }

    public function testClearCallsFlush()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $memcache
            ->shouldReceive('flush')
            ->once();

        $cache = new MemcacheCache($memcache);
        $cache->clear();
    }

    public function testMultiGetReturnsAndHasDefaultForUnsetKeys()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $defaultValue = 'defaultValue';

        $cacheData = ['foo' => 'storedValue', 'bar' => null];

        foreach ($cacheData as $expectedKey => $value) {
            $expectedValue = $value ? $value : $defaultValue;
            $memcache
                ->shouldReceive('get')
                ->with($expectedKey)
                ->once()
                ->andReturn($expectedValue);
        }


        $cache = new MemcacheCache($memcache);
        $response = $cache->getMultiple(['foo', 'bar'], $defaultValue);
        $this->assertEquals(['foo' => 'storedValue', 'bar' => $defaultValue], $response);
    }

    public function testSetMultipleReturnsExpected()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $cacheData = ['foo' => 'bar', 'baz' => 'buz'];
        foreach ($cacheData as $expectedKey => $expectedValue) {
            $memcache
                ->shouldReceive('set')
                ->with($expectedKey, $expectedValue, null, 10)
                ->once()
                ->andReturn(true);
        }

        $cache = new MemcacheCache($memcache);
        $cache->setMultiple(['foo' => 'bar', 'baz' => 'buz'], 10);
    }

    public function testDeleteMultipleReturnsExpected()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $cacheData = ['foo', 'baz'];
        foreach ($cacheData as $expectedKey) {
            $memcache
                ->shouldReceive('delete')
                ->with($expectedKey)
                ->once()
                ->andReturn(true);
        }

        $cache = new MemcacheCache($memcache);
        $cache->deleteMultiple($cacheData);
    }

    public function testHasReturnsExpected()
    {
        $memcache = Mockery::mock(Memcache::CLASS);

        $expectedKey = 'foo';

        $memcache
            ->shouldReceive('get')
            ->with($expectedKey)
            ->once()
            ->andReturn('as;dlfkajs;');

        $cache = new MemcacheCache($memcache);
        $response = $cache->has($expectedKey);

        $this->assertTrue(is_bool($response));
        $this->assertTrue($response);
    }

    /**
     * @dataProvider invalidKeyDataProvider
     */
    public function testInvalidArgumentExceptionThrownOnRequiredMethods($method, $args)
    {
        $this->expectException(InvalidArgumentException::class);
        $memcache = Mockery::mock(Memcache::CLASS)->shouldIgnoreMissing();

        $cache = new MemcacheCache($memcache);

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
        $memcache = Mockery::mock(Memcache::CLASS)->shouldIgnoreMissing();

        $cache = new MemcacheCache($memcache);

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
