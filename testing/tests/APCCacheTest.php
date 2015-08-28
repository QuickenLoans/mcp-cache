<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\DataType\Time\Clock;
use PHPUnit_Framework_TestCase;

class APCCacheTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var APCCache
     */
    private $cache;

    public function setUp()
    {
        $clock = new Clock();

        $this->cache = new APCCache($clock);
        $this->cache->clear();
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
        $out = $this->cache->set('a', 'b', -1);
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

}
