<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\DataType\Time\Clock;
use MCP\DataType\Time\TimePoint;
use Mockery;
use PHPUnit_Framework_TestCase;

class SkeletorSessionTest extends PHPUnit_Framework_TestCase
{
    public $session;
    public $clock;

    public function setUp()
    {
        $this->session = Mockery::mock('Sk\Session');
        $this->clock = new Clock('2014-04-01 12:00:00', 'UTC');
    }

    public function testSettingValueWrapsItInItem()
    {
        $item = null;
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-key-name', Mockery::on(function($v) use (&$item) {
                $item = $v;
                return true;
            }));

        $cache = new SkeletorSession($this->session, $this->clock);
        $cache->set('key-name', 'whatever');

        $this->assertInstanceOf('MCP\Cache\Item\Item', $item);
    }

    public function testSettingWithTtlStoresItemWithTimePoint()
    {
        $item = null;
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-key-name', Mockery::on(function($v) use (&$item) {
                $item = $v;
                return true;
            }));

        $cache = new SkeletorSession($this->session, $this->clock);
        $cache->set('key-name', 'whatever', 127);

        // data returned if no timepoint provided to check expiry
        $this->assertNotNull($item->data());

        // not expired
        $this->assertNotNull($item->data(new TimePoint(2014, 4, 1, 12, 0, 0, 'UTC')));
        $this->assertNotNull($item->data(new TimePoint(2014, 4, 1, 12, 2, 6, 'UTC')));

        // expired at exact time
        $this->assertNull($item->data(new TimePoint(2014, 4, 1, 12, 2, 7, 'UTC')));

        // expired after time
        $this->assertNull($item->data(new TimePoint(2014, 4, 1, 12, 2, 8, 'UTC')));
    }

    public function testSkeletorSessionCacheGettingKeyThatWasNotSetReturnsNull()
    {
        $this->session
            ->shouldReceive('get')
            ->andReturnNull();
        $this->session
            ->shouldReceive('set')
            ->never();

        $cache = new SkeletorSession($this->session, $this->clock);
        $actual = $cache->get('key');
        $this->assertNull($actual);
    }

    public function testCacheKeyIsBuiltCorrectlyWhenSuffixed()
    {
        $this->session
            ->shouldReceive('get')
            ->with('mcp-cache-KEY-suffix');
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-KEY-suffix', Mockery::any());

        $cache = new SkeletorSession($this->session, $this->clock, 'suffix');
        $cache->get('KEY');
        $this->assertTrue($cache->set('KEY', null));
    }
}
