<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Protocol;

use Stomp\Broker\Active_Mq\Active_Mq;
use Stomp\Broker\Apollo\Apollo;
use Stomp\Broker\Open_Mq\Open_Mq;
use Stomp\Broker\Rabbit_Mq\Rabbit_Mq;
use Stomp\Exception\Stomp_Exception;
use Stomp\Exception\Unexpected_Response_Exception;
use Stomp\Transport\Frame;
/**
 * Version determine stomp version and server dialect.
 *
 * @package Stomp\Protocol
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Version
{
    /**
     * Stomp Version 1.0
     */
    public const VERSION_1_0 = '1.0';
    /**
     * Stomp Version 1.1
     */
    public const VERSION_1_1 = '1.1';
    /**
     * Stomp Version 1.2
     */
    public const VERSION_1_2 = '1.2';
    /**
     * @var Frame
     */
    private $frame;
    /**
     * Version constructor.
     *
     * @throws StompException
     */
    public function __construct(Frame $frame)
    {
        if ($frame->get_command() != 'CONNECTED') {
            throw new Unexpected_Response_Exception($frame, sprintf('Expected a "CONNECTED" Frame to determine Version. Got a "%s" Frame!', $frame->get_command()));
        }
        $this->frame = $frame;
    }
    /**
     * Returns the protocol to use.
     *
     * @param string $clientId
     * @param string $default server to use of no server detected
     * @return ActiveMq|Apollo|Protocol|RabbitMq
     */
    public function get_protocol($client_id, $default = 'ActiveMQ/5.11.1')
    {
        $server = trim((string) $this->frame['server']) ?: $default;
        $version = $this->get_version();
        if (stristr($server, 'rabbitmq') !== false) {
            return new Rabbit_Mq($client_id, $version, $server);
        }
        if (stristr($server, 'apache-apollo') !== false) {
            return new Apollo($client_id, $version, $server);
        }
        if (stristr($server, 'activemq') !== false) {
            return new Active_Mq($client_id, $version, $server);
        }
        if (stristr($server, 'open message queue') !== false || stristr($server, 'openmq') !== false) {
            return new Open_Mq($client_id, $version, $server);
        }
        return new Protocol($client_id, $version, $server);
    }
    /**
     * Detected version
     *
     * @return string
     */
    public function get_version()
    {
        return $this->frame['version'] ?: self::VERSION_1_0;
    }
    /**
     * Check if version is same or newer than given one.
     *
     * @param string $version to check against
     */
    public function has_version($version): bool
    {
        return version_compare($this->get_version(), $version, '>=');
    }
}