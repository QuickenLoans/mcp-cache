<?php
/**
 * @copyright ©2015 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache\Testing;

use Psr\Log\AbstractLogger;

/**
 * @codeCoverageIgnore
 */
class MemoryLogger extends AbstractLogger
{
    public $messages;

    public function log($level, $message, array $context = array())
    {
        $this->messages[] = [$level, $message, $context];
    }
}
