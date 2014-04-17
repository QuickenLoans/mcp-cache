<?php
/**
 * @copyright ©2005—2013 Quicken Loans Inc. All rights reserved. Trade Secret,
 *    Confidential and Proprietary. Any dissemination outside of Quicken Loans
 *    is strictly prohibited.
 */

namespace MCP\Cache;

use MCP\DataType\Time\TimePoint;
use Sk\Session;

/**
 * @internal
 */
class SkeletorSession implements CacheInterface
{
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
     * @param string $key
     * @return mixed
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
     * @param string $key
     * @param mixed $value
     * @param TimePoint|null $expiration
     * @return boolean
     */
    public function set($key, $value, TimePoint $expiration = null)
    {
        $this->session->set($key, $value);
        return true;
    }
}
