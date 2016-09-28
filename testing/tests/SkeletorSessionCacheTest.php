<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use QL\MCP\Cache\Item\Item;
use QL\MCP\Common\Time\Clock;
use QL\MCP\Common\Time\TimePoint;
use Mockery;
use PHPUnit_Framework_TestCase;
use Sk\Session;

class SkeletorSessionCacheTest extends PHPUnit_Framework_TestCase
{
    public $session;
    public $clock;

    public function setUp()
    {
        $this->session = Mockery::mock(Session::class);
        $this->clock = new Clock('2014-04-01 12:00:00', 'UTC');
    }

    public function testSettingValueWrapsItInItem()
    {
        $key = 'key-name';
        $value = 'whatever';

        $item = null;
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-key-name', Mockery::on(function($v) use (&$item) {
                $item = $v;
                return true;
            }));

        $cache = new SkeletorSessionCache($this->session, $this->clock);
        $cache->set($key, $value);

        $this->assertInstanceOf(Item::class, $item);
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

        $cache = new SkeletorSessionCache($this->session, $this->clock);
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

    public function testSkeletorSessionCacheCacheGettingKeyThatWasNotSetReturnsNull()
    {
        $this->session
            ->shouldReceive('get')
            ->andReturnNull();
        $this->session
            ->shouldReceive('set')
            ->never();

        $cache = new SkeletorSessionCache($this->session, $this->clock);
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

        $cache = new SkeletorSessionCache($this->session, $this->clock, 'suffix');
        $cache->get('KEY');
        $this->assertTrue($cache->set('KEY', null));
    }

    public function testSimpleGet()
    {
        $key = 'key';
        $value = 'value';

        $session = $this->getMockBuilder('Sk\Session')
            ->disableOriginalConstructor()
            ->setMethods(['set', 'get'])
            ->getMock();

        $session->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything());

        $session->expects($this->once())
            ->method('get')
            ->with($this->anything())
            ->will($this->returnValue(new Item($value)));

        $cache = new SkeletorSessionCache($session, $this->clock);
        $cache->set($key, $value);

        $this->assertEquals($value, $cache->get($key));
    }
}
