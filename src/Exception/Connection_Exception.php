<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Exception;

/**
 * Any kind of connection problems.
 *
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Connection_Exception extends Stomp_Exception
{
    /**
     *
     * @var array
     */
    private $connection_info;
    /**
     *
     * @param string $info
     * @param ConnectionException $previous
     */
    public function __construct($info, array $connection = [], ?Connection_Exception $previous = null)
    {
        $this->connection_info = $connection;
        $host = ($previous ? $previous->get_hostname() : null) ?: $this->get_hostname();
        if ($host) {
            $info = sprintf('%s (Host: %s)', $info, $host);
        }
        parent::__construct($info, 0, $previous);
    }
    /**
     * Active used connection.
     *
     * @return array
     */
    public function get_connection_info()
    {
        return $this->connection_info;
    }
    protected function get_hostname()
    {
        return $this->connection_info['host'] ?? null;
    }
}