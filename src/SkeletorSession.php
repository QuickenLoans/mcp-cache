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
    use ValidationTrait;

    /**
     * @var Session
     */
    private $session;

    /**
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
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

        $this->session->set($key, $value);
        return true;
    }
}
