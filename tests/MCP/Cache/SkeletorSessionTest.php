<?php
/**
 * @copyright ©2005—2013 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use PHPUnit_Framework_TestCase;

class SkeletorSessionTest extends PHPUnit_Framework_TestCase
{
    public function testSkeletorSessionCacheSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = 'whatever';
        $inputKey = 'key-name';
        $mockSession = $this->mockSkeletorSession($inputValue);
        $mockSession->expects($this->once())
                    ->method('set')
                    ->with($this->equalTo($inputKey), $this->equalTo($inputValue))
                    ->will($this->returnValue(true));

        $expected = $inputValue;
        $cacher = new SkeletorSession($mockSession);
        $cacher->set($inputKey, $inputValue);
        $actual = $cacher->get($inputKey);
        $this->assertSame($expected, $actual);
    }

    public function testSkeletorSessionCacheGettingKeyThatWasNotSetReturnsNull()
    {
        $inputKey = 'key-name';

        $expected = null;
        $cacher = new SkeletorSession($this->mockSkeletorSession(null));
        $actual = $cacher->get($inputKey);
        $this->assertSame($expected, $actual);
    }

    public function mockSkeletorSession($return)
    {
        $mock = $this->getMock('Sk\Session', array('get', 'set'));
        $mock->expects($this->any())
             ->method('get')
             ->will($this->returnValue($return));

        return $mock;
    }
}