<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Testing;

use MCP\Cache\CachingTrait;

/**
 * @codeCoverageIgnore
 */
class Caching
{
    use CachingTrait {
        cache as public;
        getFromCache as public;
        setToCache as public;
    }
}
