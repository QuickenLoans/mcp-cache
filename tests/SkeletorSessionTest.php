<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Mockery;
use PHPUnit_Framework_TestCase;

class SkeletorSessionTest extends PHPUnit_Framework_TestCase
{
    public $session;

    public function setUp()
    {
        $this->session = Mockery::mock('Sk\Session');
    }

    public function testSkeletorSessionCacheSettingAKeyAndGetSameKeyResultsInOriginalValue()
    {
        $inputValue = 'whatever';

        $this->session
            ->shouldReceive('get')
            ->andReturn($inputValue);
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-key-name', $inputValue)
            ->andReturn($inputValue);

        $expected = $inputValue;

        $cache = new SkeletorSession($this->session);
        $cache->set('key-name', $inputValue);

        $actual = $cache->get('key-name');
        $this->assertSame($expected, $actual);
    }

    public function testSkeletorSessionCacheGettingKeyThatWasNotSetReturnsNull()
    {
        $this->session
            ->shouldReceive('get')
            ->andReturnNull();
        $this->session
            ->shouldReceive('set')
            ->never();

        $cache = new SkeletorSession($this->session);
        $actual = $cache->get('key');
        $this->assertNull($actual);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Resources cannot be cached
     */
    public function testCachingResourceBlowsUp()
    {
        $cache = new SkeletorSession($this->session);
        $actual = $cache->set('key', fopen('php://stdout', 'w'));
    }

    public function testCacheKeyIsBuiltCorrectlyWhenSuffixed()
    {
        $this->session
            ->shouldReceive('get')
            ->with('mcp-cache-KEY-suffix');
        $this->session
            ->shouldReceive('set')
            ->with('mcp-cache-KEY-suffix', Mockery::any());

        $cache = new SkeletorSession($this->session, 'suffix');
        $cache->get('KEY');
        $this->assertTrue($cache->set('KEY', null));
    }
}
