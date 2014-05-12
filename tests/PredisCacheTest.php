<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use DateTime;
use DateTimeZone;
use Mockery;
use PHPUnit_Framework_TestCase;

class PredisCacheTest extends PHPUnit_Framework_TestCase
{
    public $predis;

    public function setUp()
    {
        $this->predis = Mockery::mock('Predis\Client');
    }

    public function testSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = ['myval' => 1];
        $expected = $inputValue;

        $setValue = null;
        $this->predis
            ->shouldReceive('set')
            ->with('mcp-cache:mykey', Mockery::on(function($v) use (&$setValue) {
                $setValue = $v;
                return true;
            }));

        $this->predis
            ->shouldReceive('get')
            ->with('mcp-cache:mykey')
            ->andReturn(serialize($inputValue));

        $cache = new PredisCache($this->predis);
        $cache->set('mykey', $inputValue);

        // assert set value is properly serialized
        $this->assertSame(serialize($inputValue), $setValue);

        $actual = $cache->get('mykey');

        // assert get value is properly deserialized
        $this->assertSame($expected, $actual);
    }

    public function testSettingWithExpiration()
    {
        $inputValue = new DateTime('2015-03-15 4:30:00', new DateTimeZone('UTC'));

        $setValue = null;
        $this->predis
            ->shouldReceive('setex')
            ->with('mcp-cache:test', Mockery::on(function($v) use (&$setValue) {
                $setValue = $v;
                return true;
            }), 60);

        $cache = new PredisCache($this->predis);
        $cache->set('test', $inputValue, 60);

        // assert set value is properly serialized
        $this->assertSame(serialize($inputValue), $setValue);
    }

    public function testSettingNullDeletesKeyInstead()
    {
        $this->predis
            ->shouldReceive('del')
            ->with('mcp-cache:test')
            ->once();

        $cache = new PredisCache($this->predis);
        $result = $cache->set('test', null);

        $this->assertTrue($result);
    }

    public function testKeyIsSalted()
    {
        $this->predis
            ->shouldReceive('get')
            ->with('mcp-cache:test:salty')
            ->andReturnNull()
            ->once();

        $cache = new PredisCache($this->predis, 'salty');
        $value = $cache->get('test');

        // non-string values are not unserialized
        $this->assertSame(null, $value);
    }
}