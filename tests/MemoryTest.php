<?php
/**
 * @copyright ©2005—2013 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use PHPUnit_Framework_TestCase;

class MemoryTest extends PHPUnit_Framework_TestCase
{
    public function testSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = array('myval' => 1);
        $inputKey = 'mykey';
        $expected = $inputValue;
        $cacheObj = new Memory;
        $cacheObj->set($inputKey, $inputValue);
        $actual = $cacheObj->get($inputKey);
        $this->assertSame($expected, $actual);
    }

    public function testGettingKeyThatWasNotSetReturnsNullAndNoError()
    {
        $inputKey = 'key-with-no-value';
        $expected = null;
        $cacheObj = new Memory;
        $actual = $cacheObj->get($inputKey);
        $this->assertSame($expected, $actual);
    }
}
