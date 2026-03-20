<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Active_Mq\Mode;

use Stomp\Broker\Active_Mq\Active_Mq;
use Stomp\Broker\Active_Mq\Options;
use Stomp\Broker\Exception\Unsupported_Broker_Exception;
use Stomp\Client;
/**
 * ActiveMqMode
 *
 * @package Stomp\Broker\ActiveMq\Mode
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
abstract class Active_Mq_Mode
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var Options
     */
    protected $options;
    /**
     * ActiveMqMode constructor.
     */
    public function __construct(Client $client)
    {
        $this->options = new Options();
        $this->client = $client;
    }
    /**
     * @return ActiveMq
     * @throws \Stomp\Broker\Exception\UnsupportedBrokerException
     */
    protected function get_protocol()
    {
        $protocol = $this->client->get_protocol();
        if (!$protocol instanceof Active_Mq) {
            throw new Unsupported_Broker_Exception($protocol, Active_Mq::class);
        }
        return $protocol;
    }
    /**
     * @return Options
     */
    public function get_options()
    {
        return $this->options;
    }
    /**
     * @return ActiveMqMode
     */
    public function set_options(Options $options)
    {
        $this->options = $options;
        return $this;
    }
}