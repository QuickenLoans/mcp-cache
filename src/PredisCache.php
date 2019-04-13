<?php
/**
 * @copyright (c) 2016 Quicken Loans Inc.
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace QL\MCP\Cache;

use Predis\Client;
use QL\MCP\Cache\Utility\KeySaltingTrait;
use QL\MCP\Cache\Utility\MaximumTTLTrait;

class PredisCache implements CacheInterface
{
    use KeySaltingTrait;
    use MaximumTTLTrait;

    /**
     * Properties read and used by the salting trait.
     *
     * @var string
     */
    const PREFIX = 'mcp-cache-' . CacheInterface::VERSION;
    const DELIMITER = ':';

    /**
     * @var Client
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
     * This cacher performs serialization and unserialization. All string responses from redis will be unserialized.
     *
     * For this reason, only mcp cachers should attempt to retrieve data cached by mcp cachers.
     *
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $raw = $this->predis->get($key);

        // every response should be a php serialized string.
        // values not matching this pattern will explode.
        // missing data should return null, which is not unserialized.
        $value = (is_string($raw)) ? unserialize($raw) : $raw;

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = $this->salted($key, $this->suffix);

        // handle deletions
        if ($value === null) {
            $this->predis->del($key);
            return true;
        }

        $value = serialize($value);

        // Resolve the TTL to use
        $ttl = $this->determineTTL($ttl);

        // set with expiration
        if ($ttl > 0) {
            $this->predis->setex($key, $ttl, $value);
            return true;
        }

        // set with no expiration
        $this->predis->set($key, $value);
        return true;
    }
}
