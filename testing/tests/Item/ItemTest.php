<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Item;

use QL\MCP\Common\Time\TimePoint;
use PHPUnit_Framework_TestCase;

class ItemTest extends PHPUnit_Framework_TestCase
{
    public function testItemWithoutExpiryDoesNotExpire()
    {
        $item = new Item('data');
        $this->assertSame('data', $item->data());
    }

    public function testItemDataNotExpiredIfCurrentTimeProvidedButNoExpirySet()
    {
        $accessed = new TimePoint(2014, 4, 1, 11, 0, 0, 'UTC');

        $item = new Item('data');
        $this->assertSame('data', $item->data($accessed));
    }

    public function testItemWithExpiryDoesNotExpireIfNoCurrentTimeProvided()
    {
        $expiry = new TimePoint(2014, 4, 1, 12, 0, 0, 'UTC');
        $item = new Item('data', $expiry);

        $this->assertSame('data', $item->data());
    }

    public function testItemDataNotExpired()
    {
        $expiry = new TimePoint(2014, 4, 1, 12, 0, 0, 'UTC');
        $accessed = new TimePoint(2014, 4, 1, 11, 0, 0, 'UTC');

        $item = new Item('data', $expiry);
        $this->assertSame('data', $item->data($accessed));
    }

    public function testItemDataExpired()
    {
        $expiry = new TimePoint(2014, 4, 1, 12, 0, 0, 'UTC');
        $accessed = new TimePoint(2014, 4, 1, 13, 0, 0, 'UTC');

        $item = new Item('data', $expiry);
        $this->assertSame(null, $item->data($accessed));
    }

    public function testTTLisStoredInOriginalForm()
    {
        $expiry = new TimePoint(2014, 4, 1, 12, 0, 0, 'UTC');

        $item = new Item('data', $expiry, 3600);
        $this->assertSame(3600, $item->ttl());
    }

    /**
     * @expectedException MCP\Cache\Exception
     * @expectedExceptionMessage Resources cannot be cached
     */
    public function testCachingResourceBlowsUp()
    {
        new Item(fopen('php://stdout', 'w'));
    }
}
