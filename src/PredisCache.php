<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\Cache\Utility\KeySaltingTrait;
use Predis\Client;

/**
 * @internal
 */
class PredisCache implements CacheInterface
{
    use KeySaltingTrait;

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache';
    const DELIMITER = ':';

    /**
     * @var client
     */
    private $predis;

    /**
     * @var string|null
     */
    private $suffix;

    /**
     * An optional suffix can be provided which will be appended to the key
     * used to store and retrieve data. This can be used to throw away cached
     * data with a code push or other configuration change.
     *
     * @param Client $client
     * @param string|null $suffix
     */
    public function __construct(Client $client, $suffix = null)
    {
        $this->predis = $client;
        $this->suffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $raw = $this->predis->get($key);
        $value = (is_string($raw)) ? unserialize($raw) : $raw;

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = $this->salted($key, $this->suffix);
        $value = serialize($value);

        if ($ttl > 0) {
            return $this->predis->setex($key, $value, $ttl);
        }

        return $this->predis->set($key, $value);
    }
}
