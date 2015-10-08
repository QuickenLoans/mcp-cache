<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Memcached;
use Mockery;
use PHPUnit_Framework_TestCase;

class MemcachedCacheTest extends PHPUnit_Framework_TestCase
{
    public function testSettingAKeyWithoutExpirationUsesZeroAsDefault()
    {
        $memcached = Mockery::mock(Memcached::CLASS);

        $memcached
            ->shouldReceive('set')
            ->with('mykey', 'testvalue', 0)
            ->once();

        $cache = new MemcachedCache($memcached);
        $cache->set('mykey', 'testvalue');
    }

    public function testGettingKey()
    {
        $memcached = Mockery::mock(Memcached::CLASS);

        $memcached
            ->shouldReceive('get')
            ->with('mykey')
            ->andReturn('testvalue2')
            ->once();

        $cache = new MemcachedCache($memcached);
        $actual = $cache->get('mykey');

        $this->assertSame('testvalue2', $actual);
    }

    public function testSettingWithExpiration()
    {
        $memcached = Mockery::mock(Memcached::CLASS);

        $memcached
            ->shouldReceive('set')
            ->with('mykey', 'testvalue', 600)
            ->once();

        $cache = new MemcachedCache($memcached);
        $cache->set('mykey', 'testvalue', 600);
    }

    public function testDeleteIsUsedIfValueIsNull()
    {
        $memcached = Mockery::mock(Memcached::CLASS);

        $memcached
            ->shouldReceive('delete')
            ->with('mykey')
            ->once();

        $cache = new MemcachedCache($memcached);
        $cache->set('mykey', null, 600);
    }
}
