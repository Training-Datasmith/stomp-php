<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Active_Mq;

use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Frame;
/**
 * ActiveMq Stomp dialect.
 *
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Active_Mq extends Protocol
{
    /**
     * Prefetch Size for subscriptions.
     *
     * @var int
     */
    private $prefetch_size = 1;
    /**
     * ActiveMq subscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @param string $ack
     * @param string $selector
     * @param boolean|false $durable durable subscription
     * @return Frame
     */
    public function get_subscribe_frame($destination, $subscription_id = null, $ack = 'auto', $selector = null, $durable = false)
    {
        $frame = parent::get_subscribe_frame($destination, $subscription_id, $ack, $selector);
        $frame['activemq.prefetchSize'] = $this->prefetch_size;
        if ($durable) {
            $frame['activemq.subscriptionName'] = $this->get_client_id();
            $frame['durable-subscriber-name'] = $subscription_id;
        }
        return $frame;
    }
    /**
     * ActiveMq unsubscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @param bool|false $durable
     * @return Frame
     */
    public function get_unsubscribe_frame($destination, $subscription_id = null, $durable = false)
    {
        $frame = parent::get_unsubscribe_frame($destination, $subscription_id);
        if ($durable) {
            $frame['activemq.subscriptionName'] = $this->get_client_id();
            $frame['durable-subscriber-name'] = $subscription_id;
        }
        return $frame;
    }
    /**
     * @inheritdoc
     */
    public function get_ack_frame(Frame $frame, $transaction_id = null)
    {
        $ack = $this->create_frame('ACK');
        $ack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            $ack['id'] = $frame['ack'] ?: $frame->get_message_id();
        } else {
            $ack['message-id'] = $frame['ack'] ?: $frame->get_message_id();
            if ($this->has_version(Version::VERSION_1_1)) {
                $ack['subscription'] = $frame['subscription'];
            }
        }
        return $ack;
    }
    /**
     * @inheritdoc
     */
    public function get_nack_frame(Frame $frame, $transaction_id = null, $requeue = null)
    {
        if ($requeue !== null) {
            throw new \LogicException('requeue header not supported by ActiveMQ. Please read ActiveMQ DLQ documentation.');
        }
        $nack = $this->create_frame('NACK');
        $nack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            $nack['id'] = $frame['ack'] ?: $frame->get_message_id();
        } else {
            $nack['message-id'] = $frame['ack'] ?: $frame->get_message_id();
            if ($this->has_version(Version::VERSION_1_1)) {
                $nack['subscription'] = $frame['subscription'];
            }
        }
        return $nack;
    }
    /**
     * Prefetch Size for subscriptions
     *
     * @return int
     */
    public function get_prefetch_size()
    {
        return $this->prefetch_size;
    }
    /**
     * Prefetch Size for subscriptions
     *
     * @param int $prefetchSize
     */
    public function set_prefetch_size($prefetch_size): self
    {
        $this->prefetch_size = $prefetch_size;
        return $this;
    }
}