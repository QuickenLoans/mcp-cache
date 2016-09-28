<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache\Item;

use QL\MCP\Cache\Exception as CacheException;
use QL\MCP\Common\Time\Clock;
use QL\MCP\Common\Time\TimePoint;
use PHPUnit_Framework_TestCase;

class ItemTest extends PHPUnit_Framework_TestCase
{
    public $clock;

    public function setUp()
    {
        $this->clock = new Clock;
    }

    public function testItemWithoutExpiryDoesNotExpire()
    {
        $item = new Item('data');
        $this->assertSame('data', $item->data());
    }

    public function testItemDataNotExpiredIfCurrentTimeProvidedButNoExpirySet()
    {
        $accessed = $this->clock->fromString('2014-04-01T11:00:00Z');

        $item = new Item('data');
        $this->assertSame('data', $item->data($accessed));
    }

    public function testItemWithExpiryDoesNotExpireIfNoCurrentTimeProvided()
    {
        $expiry = $this->clock->fromString('2014-04-01T12:00:00Z');

        $item = new Item('data', $expiry);

        $this->assertSame('data', $item->data());
    }

    public function testItemDataNotExpired()
    {
        $expiry = $this->clock->fromString('2014-04-01T12:00:00Z');
        $accessed = $this->clock->fromString('2014-04-01T11:00:00Z');

        $item = new Item('data', $expiry);
        $this->assertSame('data', $item->data($accessed));
    }

    public function testItemDataExpired()
    {
        $expiry = $this->clock->fromString('2014-04-01T12:00:00Z');
        $accessed = $this->clock->fromString('2014-04-01T13:00:00Z');

        $item = new Item('data', $expiry);
        $this->assertSame(null, $item->data($accessed));
    }

    public function testTTLisStoredInOriginalForm()
    {
        $expiry = $this->clock->fromString('2014-04-01T12:00:00Z');

        $item = new Item('data', $expiry, 3600);
        $this->assertSame(3600, $item->ttl());
    }

    public function testCachingResourceBlowsUp()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Resources cannot be cached');

        new Item(fopen('php://stdout', 'w'));
    }
}
