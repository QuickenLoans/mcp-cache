<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use DateTime;
use DateTimeZone;
use Mockery;
use PHPUnit_Framework_TestCase;
use Predis\Client;

class PredisCacheTest extends PHPUnit_Framework_TestCase
{
    public $predis;

    public function setUp()
    {
        $this->predis = Mockery::mock(Client::class);
        $this->predis
            ->shouldReceive('get')
            ->with('mcp-cache-' . CacheInterface::VERSION . '-generation')
            ->andReturn(1);
    }

    /**
     * @dataProvider invalidKeyDataProvider
     */
    public function testInvalidArgumentExceptionThrownOnRequiredMethods($method, $args)
    {
        $this->expectException(InvalidArgumentException::class);
        $mock = Mockery::mock(Client::class)->shouldIgnoreMissing();

        $cache = new PredisCache($mock);

        $cache->$method(...$args);
    }

    public function testSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = ['myval' => 1];
        $expected = $inputValue;

        $expectedKey = sprintf('mcp-cache-%s:mykey:1', CacheInterface::VERSION);

        $setValue = null;
        $this->predis
            ->shouldReceive('set')
            ->with($expectedKey, Mockery::on(function ($v) use (&$setValue) {
                $setValue = $v;

                return true;
            }));

        $this->predis
            ->shouldReceive('get')
            ->with($expectedKey)
            ->andReturn(serialize($inputValue));

        $cache = new PredisCache($this->predis);
        $cache->set('mykey', $inputValue);

        // assert set value is properly serialized
        $this->assertSame(serialize($inputValue), $setValue);

        $actual = $cache->get('mykey');

        // assert get value is properly deserialized
        $this->assertSame($expected, $actual);
    }

    public function testSettingWithExpiration()
    {
        $inputValue = new DateTime('2015-03-15 4:30:00', new DateTimeZone('UTC'));

        $expectedKey = sprintf('mcp-cache-%s:test:1', CacheInterface::VERSION);
        $setValue = null;
        $this->predis
            ->shouldReceive('setex')
            ->with($expectedKey, 60, Mockery::on(function ($v) use (&$setValue) {
                $setValue = $v;

                return true;
            }));

        $cache = new PredisCache($this->predis);
        $cache->setMaximumTtl(90);
        $cache->set('test', $inputValue, 60);

        // assert set value is properly serialized
        $this->assertSame(serialize($inputValue), $setValue);
    }

    public function testSettingNullDeletesKeyInstead()
    {
        $expectedKey = sprintf('mcp-cache-%s:test:1', CacheInterface::VERSION);
        $this->predis
            ->shouldReceive('del')
            ->with($expectedKey)
            ->once();

        $cache = new PredisCache($this->predis);
        $result = $cache->set('test', null);

        $this->assertTrue($result);
    }

    public function testKeyIsSalted()
    {
        $expectedKey = sprintf('mcp-cache-%s:test:1:salty', CacheInterface::VERSION);

        $this->predis
            ->shouldReceive('get')
            ->with($expectedKey)
            ->andReturnNull()
            ->once();

        $cache = new PredisCache($this->predis, 'salty');
        $value = $cache->get('test');

        // non-string values are not unserialized
        $this->assertSame(null, $value);
    }

    public function testMaxTTLisUsedIfNoTtlIsProvidedAtRuntime()
    {
        $expectedKey = sprintf('mcp-cache-%s:test:1', CacheInterface::VERSION);
        $setValue = null;

        $this->predis
            ->shouldReceive('setex')
            ->with($expectedKey, 60, Mockery::on(function ($v) use (&$setValue) {
                $setValue = $v;

                return true;
            }));


        $cache = new PredisCache($this->predis);
        $cache->setMaximumTtl(60);

        $cache->set('test', 123);

        // assert set value is properly serialized
        $this->assertSame(serialize(123), $setValue);
    }

    public function testMaxTTLisUsedIfRuntimeExpirationExceedsMaxValue()
    {
        $expectedKey = sprintf('mcp-cache-%s:test:1', CacheInterface::VERSION);
        $setValue = null;
        $this->predis
            ->shouldReceive('setex')
            ->with($expectedKey, 60, Mockery::on(function ($v) use (&$setValue) {
                $setValue = $v;

                return true;
            }));


        $cache = new PredisCache($this->predis);
        $cache->setMaximumTtl(60);

        $cache->set('test', 'data', 80);

        // assert set value is properly serialized
        $this->assertSame(serialize('data'), $setValue);
    }

    public function testDeleteCallsWithSaltedKey()
    {
        $expectedKey = sprintf('mcp-cache-%s:test:1', CacheInterface::VERSION);

        $this->predis
            ->shouldReceive('del')
            ->with($expectedKey);


        $cache = new PredisCache($this->predis);

        $cache->delete('test');
    }

    public function testClearSetsCacheGenerationToCache()
    {
        $this->predis->shouldReceive('set')->with(PredisCache::GENERATION_KEY, 2);

        $cache = new PredisCache($this->predis);
        $cache->clear();
    }

    public function testGetMultipleReturnsCorrectKeys()
    {
        $keys = ['foo', 'bar'];

        $expectedKeys = [
            sprintf('mcp-cache-%s:foo:1', CacheInterface::VERSION),
            sprintf('mcp-cache-%s:bar:1', CacheInterface::VERSION)
        ];

        $this->predis->shouldReceive('mget')->with($expectedKeys)->andReturn([serialize('fooReturn'), null]);

        $cache = new PredisCache($this->predis);
        $response = $cache->getMultiple($keys);

        $this->assertEquals(['foo' => 'fooReturn', 'bar' => null], $response);
    }

    public function testGetMultipleReturnsCorrectKeysWithProvidedDefaults()
    {
        $keys = ['foo'];

        $expectedKeys = [
            sprintf('mcp-cache-%s:foo:1', CacheInterface::VERSION),
        ];

        $this->predis->shouldReceive('mget')->with($expectedKeys)->andReturn([null]);

        $cache = new PredisCache($this->predis);
        $response = $cache->getMultiple($keys, 'defaultValue');

        $this->assertEquals(['foo' => 'defaultValue'], $response);
    }

    public function testSetMultipleCallsWithSetValues()
    {
        $inputValue = ['foo' => 1];
        $expected = $inputValue;

        $this->predis->shouldReceive('multi');
        $this->predis->shouldReceive('exec');

        $expectedKey = sprintf('mcp-cache-%s:foo:1', CacheInterface::VERSION);
        $setValue = null;
        $this->predis
            ->shouldReceive('setex')
            ->with($expectedKey, 60, Mockery::on(function ($v) use (&$setValue) {
                $setValue = $v;

                return true;
            }));

        $cache = new PredisCache($this->predis);
        $cache->setMaximumTtl(90);
        $cache->setMultiple($inputValue, 60);

        // assert set value is properly serialized
        $this->assertSame(serialize(1), $setValue);
    }

    public function testDeleteMultipleCallsDeleteWithAllExpectedValues()
    {
        $inputValue = ['foo', 'bar'];
        $expectedKeys = [
            $expectedKey = sprintf('mcp-cache-%s:foo:1', CacheInterface::VERSION),
            $expectedKey = sprintf('mcp-cache-%s:bar:1', CacheInterface::VERSION)
        ];

        $this->predis->shouldReceive('multi');
        $this->predis->shouldReceive('exec');

        foreach ($expectedKeys as $expectedKey) {
            $this->predis
                ->shouldReceive('del')
                ->with($expectedKey);
        }

        $cache = new PredisCache($this->predis);
        $this->assertTrue($cache->deleteMultiple($inputValue));
    }

    public function testHasReturnsPredisExistsCallAsBoolean()
    {
        $this->predis->shouldReceive('exists')->andReturn(1);

        $cache = new PredisCache($this->predis);
        $exists = $cache->has('foo');

        $this->assertTrue(is_bool($exists));
        $this->assertTrue($exists);
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
        $mock = Mockery::mock(Client::class)->shouldIgnoreMissing();

        $cache = new PredisCache($mock);
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


