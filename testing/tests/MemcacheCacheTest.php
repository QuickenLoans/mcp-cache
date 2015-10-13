<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Memcache;
use Mockery;
use PHPUnit_Framework_TestCase;

class MemcacheCacheTest extends PHPUnit_Framework_TestCase
{
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
