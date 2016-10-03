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

        $expectedKey = sprintf('mcp-cache-%s-key-name', CacheInterface::VERSION);

        $item = null;
        $this->session
            ->shouldReceive('set')
            ->with($expectedKey, Mockery::on(function($v) use (&$item) {
                $item = $v;
                return true;
            }));

        $cache = new SkeletorSessionCache($this->session, $this->clock);
        $cache->set($key, $value);

        $this->assertInstanceOf(Item::class, $item);
    }

    public function testSettingWithTtlStoresItemWithTimePoint()
    {
        $expectedKey = sprintf('mcp-cache-%s-key-name', CacheInterface::VERSION);

        $item = null;
        $this->session
            ->shouldReceive('set')
            ->with($expectedKey, Mockery::on(function($v) use (&$item) {
                $item = $v;
                return true;
            }));

        $cache = new SkeletorSessionCache($this->session, $this->clock);
        $cache->set('key-name', 'whatever', 127);

        // data returned if no timepoint provided to check expiry
        $this->assertNotNull($item->data());

        // not expired
        $data = $item->data($this->clock->fromString('2014-04-01T12:00:00Z'));
        $this->assertNotNull($data);

        $data = $item->data($this->clock->fromString('2014-04-01T12:02:06Z'));
        $this->assertNotNull($data);

        // expired at exact time
        $data = $item->data($this->clock->fromString('2014-04-01T12:02:07Z'));
        $this->assertNull($data);

        // expired after time
        $data = $item->data($this->clock->fromString('2014-04-01T12:02:08Z'));
        $this->assertNull($data);
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
        $expectedKey = sprintf('mcp-cache-%s-KEY-suffix', CacheInterface::VERSION);

        $this->session
            ->shouldReceive('get')
            ->with($expectedKey);
        $this->session
            ->shouldReceive('set')
            ->with($expectedKey, Mockery::any());

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
