<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
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
