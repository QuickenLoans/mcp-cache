<?php
/**
 * @copyright ©2005—2013 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Mockery;
use PHPUnit_Framework_TestCase;

class SkeletorSessionTest extends PHPUnit_Framework_TestCase
{
    public function testSkeletorSessionCacheSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = 'whatever';
        $inputKey = 'key-name';

        $session = Mockery::mock('Sk\Session');
        $session
            ->shouldReceive('get')
            ->once()
            ->andReturn($inputValue);
        $session
            ->shouldReceive('set')
            ->once()
            ->with($inputKey, $inputValue)
            ->andReturn($inputValue);

        $expected = $inputValue;
        $cacher = new SkeletorSession($session);
        $cacher->set($inputKey, $inputValue);
        $actual = $cacher->get($inputKey);
        $this->assertSame($expected, $actual);
    }

    public function testSkeletorSessionCacheGettingKeyThatWasNotSetReturnsNull()
    {
        $inputKey = 'key-name';
        $expected = null;

        $session = Mockery::mock('Sk\Session');
        $session
            ->shouldReceive('get')
            ->once()
            ->andReturnNull();
        $session
            ->shouldReceive('set')
            ->never();

        $cacher = new SkeletorSession($session);
        $actual = $cacher->get($inputKey);
        $this->assertSame($expected, $actual);
    }
}
