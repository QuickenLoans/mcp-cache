<?php
/**
 * @copyright ©2017 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace QL\MCP\Cache\Utility;

use QL\MCP\Cache\InvalidArgumentException;

/**
 * @internal
 */
trait CacheInputValidationTrait
{
    // 4 backslashes to match one literal back slash seems bananas.
    private $invalidKeyRegex = '/\{|\}|\(|\)|\/|\\\\|\@|:/';
    private $invalidKeyMsg = 'Cache key: `%s` contains illegal characters -- keys MUST NOT contain any of the following characters: `{}()/\@:`';

    private $invalidIterableMsg = 'Invalid keys: %s Keys should be an array of strings';

    /**
     * A regex check to make sure that cache keys do not contain any invalid characters
     *
     * According to the psr 16 spec keys MUST NOT contain any of the following (not including the ticks) `{}()\/:`
     *
     * @param $key
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    private function validateKey($key)
    {
        if (preg_match($this->invalidKeyRegex, $key)) {
            throw new InvalidArgumentException(sprintf($this->invalidKeyMsg, $key));
        }

        return $key;
    }

    private function validateIterable($iterable)
    {
        if (!$iterable instanceof \Traversable && !is_array($iterable)) {
            throw new InvalidArgumentException(sprintf($this->invalidIterableMsg, var_export($iterable, true)));
        }

        return ($iterable instanceof \Traversable) ? iterator_to_array($iterable) : $iterable;
    }
}
