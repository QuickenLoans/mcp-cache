<?php
/**
 * @copyright ©2014 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use Sk\Session;

/**
 * @internal
 */
class SkeletorSession implements CacheInterface
{
    use KeySaltingTrait;
    use ValidationTrait;

    /**
     * @var string
     */
    const PREFIX = 'mcp-cache';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var string|null
     */
    private $suffix;

    /**
     * An optional suffix can be provided which will be appended to the key
     * used to store and retrieve data. This can be used to throw away cached
     * data with a code push or other configuration change.
     *
     * @param Session $session
     * @param string|null $suffix
     */
    public function __construct(Session $session, $suffix = null)
    {
        $this->session = $session;
        $this->suffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->salted($key, $this->suffix);

        $data = $this->session->get($key);
        if (!isset($data)) {
            return null;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->validateCacheability($value);
        $key = $this->salted($key, $this->suffix);

        $this->session->set($key, $value);
        return true;
    }
}
