<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache\Testing;

use QL\MCP\Cache\CachingTrait;

/**
 * @codeCoverageIgnore
 */
class CachingStub
{
    use CachingTrait {
        cache as public;
        getFromCache as public;
        setToCache as public;
        clearCache as public;
    }
}
