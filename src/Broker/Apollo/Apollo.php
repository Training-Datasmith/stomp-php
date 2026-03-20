<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Apollo;

use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Frame;
/**
 * Apollo Stomp dialect.
 *
 * @package Stomp
 * @author András Rutkai <riskawarrior@live.com>
 */
class Apollo extends Protocol
{
    /**
     * Apollo subscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @param string $ack
     * @param string $selector
     * @param boolean $durable durable subscription
     * @return \Stomp\Transport\Frame
     */
    public function get_subscribe_frame($destination, $subscription_id = null, $ack = 'auto', $selector = null, $durable = false)
    {
        $frame = parent::get_subscribe_frame($destination, $subscription_id, $ack, $selector);
        if ($this->has_client_id() && $durable) {
            $frame['persistent'] = 'true';
        }
        return $frame;
    }
    /**
     * Apollo unsubscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @param bool|false $durable
     * @return \Stomp\Transport\Frame
     */
    public function get_unsubscribe_frame($destination, $subscription_id = null, $durable = false)
    {
        $frame = parent::get_unsubscribe_frame($destination, $subscription_id);
        if ($durable) {
            $frame['persistent'] = 'true';
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
            throw new \LogicException('requeue header not supported');
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
}