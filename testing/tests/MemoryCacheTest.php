<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Psr\SimpleCache\InvalidArgumentException;
use QL\MCP\Cache\Exception as CacheException;
use PHPUnit_Framework_TestCase;

class MemoryCacheTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider invalidKeyDataProvider
     */
    public function testInvalidArgumentExceptionThrownOnRequiredMethods($method, $args)
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = new MemoryCache();

        $cache->$method(...$args);
    }

    public function testSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = ['myval' => 1];
        $expected = $inputValue;

        $cache = new MemoryCache;
        $cache->set('mykey', $inputValue);

        $actual = $cache->get('mykey');
        $this->assertSame($expected, $actual);
    }

    public function testGettingKeyThatWasNotSetReturnsNullAndNoError()
    {
        $inputKey = 'key-with-no-value';

        $cache = new MemoryCache;

        $actual = $cache->get($inputKey);
        $this->assertNull($actual);
    }

    public function testGettingKeyThatWasNotSetReturnsDefaultAndNoError()
    {
        $inputKey = 'key-with-no-value';
        $default = 'foo';

        $cache = new MemoryCache;

        $actual = $cache->get($inputKey, $default);
        $this->assertSame($actual, $default);
    }

    public function testCachingResourceBlowsUp()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Resources cannot be cached');

        $cache = new MemoryCache;
        $actual = $cache->set('key', fopen('php://stdout', 'w'));
    }

    public function testDeleteRemovesItemFromCache()
    {
        $cache = new MemoryCache;

        $cache->set('foo', 'bar');
        $this->assertTrue($cache->delete('foo'));
        $this->assertNull($cache->get('foo'));
    }

    public function testClear()
    {
        $cache = new MemoryCache;

        $cache->set('foo', 'bar');
        $cache->set('fizz', 'buzz');
        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('foo'));
        $this->assertNull($cache->get('fizz'));
    }

    public function testGetMultiple()
    {
        $cache = new MemoryCache();
        $cache->set('foo', 'bar');
        $cache->set('fizz', 'buzz');

        $return = $cache->getMultiple(new \ArrayIterator(['foo', 'fizz']));

        $this->assertSame('bar', $return['foo']);
        $this->assertSame('buzz', $return['fizz']);
    }

    public function testGetMultipleReturnsDefaultsWhenKeyDoesNotExist()
    {
        $cache = new MemoryCache();

        $return = $cache->getMultiple(['foo', 'fizz'], 'defaultValue');

        $this->assertSame('defaultValue', $return['foo']);
        $this->assertSame('defaultValue', $return['fizz']);
    }

    public function testSetMultiple()
    {
        $multipleData = ['foo' => 'bar', 'fizz' => 'buzz'];

        $cache = new MemoryCache();
        $cache->setMultiple(new \ArrayIterator($multipleData));

        $return = $cache->getMultiple(array_keys($multipleData));

        $this->assertEquals($return, $multipleData);
    }

    public function testDeleteMultiple()
    {
        $multipleData = ['foo' => 'bar', 'fizz' => 'buzz'];

        $cache = new MemoryCache();
        $cache->setMultiple($multipleData);
        $cache->deleteMultiple(new \ArrayIterator(array_keys($multipleData)));

        $return = $cache->getMultiple(array_keys($multipleData));

        foreach (array_values($return) as $value) {
            $this->assertNull($value);
        }
    }

    public function testHasKeyReturnsTrueIfKeyExists()
    {
        $cache = new MemoryCache();
        $cache->set('foo', 'bar');

        $this->assertTrue($cache->has('foo'));
    }

    public function testHasKeyReturnsFalseIfNoKeyExists()
    {
        $cache = new MemoryCache();

        $this->assertFalse($cache->has('foo'));
    }


    /**
     * Testing out that all the multiple gets and setters blow up if passed
     * a none iterable
     *
     * @dataProvider invalidIterableForMultipleCallsDataProvider
     */
    public function testMutliCallsErrorOnInvalidArgs($method, $arg)
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = new MemoryCache();
        $cache->$method($arg);
    }

    public function invalidIterableForMultipleCallsDataProvider()
    {
        return [
            ['getMultiple', 'singleWord'],
            ['deleteMultiple', 'singleWord'],
            ['setMultiple' , 'singleword']
        ];
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
}
