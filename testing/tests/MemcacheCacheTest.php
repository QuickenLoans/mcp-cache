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
}
