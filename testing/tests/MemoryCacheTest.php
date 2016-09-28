<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Exception as CacheException;
use PHPUnit_Framework_TestCase;

class MemoryCacheTest extends PHPUnit_Framework_TestCase
{
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

    public function testCachingResourceBlowsUp()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Resources cannot be cached');

        $cache = new MemoryCache;
        $actual = $cache->set('key', fopen('php://stdout', 'w'));
    }

    public function testGetNotFound()
    {
        $cache = new MemoryCache;

        $this->assertEquals(null, $cache->get('aaaaaaaaa'));
    }
}
