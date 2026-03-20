<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Rabbit_Mq;

use Stomp\Exception\Stomp_Exception;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Frame;
/**
 * RabbitMq Stomp dialect.
 *
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Rabbit_Mq extends Protocol
{
    /**
     * Prefetch Size for subscriptions.
     *
     * @var int
     */
    private $prefetch_count = 1;
    /**
     * RabbitMq subscribe frame.
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
        $frame['prefetch-count'] = $this->prefetch_count;
        if ($durable) {
            $frame['persistent'] = 'true';
        }
        return $frame;
    }
    /**
     * RabbitMq unsubscribe frame.
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
     * Prefetch Count for subscriptions
     *
     * @return int
     */
    public function get_prefetch_count()
    {
        return $this->prefetch_count;
    }
    /**
     * Prefetch Count for subscriptions
     *
     * @param int $prefetchCount
     */
    public function set_prefetch_count($prefetch_count): void
    {
        $this->prefetch_count = $prefetch_count;
    }
    /**
     * Get message not acknowledge frame.
     *
     * @param string $transactionId
     * @param bool $requeue Requeue header supported on RabbitMQ >= 3.4, ignored in prior versions
     * @return \Stomp\Transport\Frame
     * @throws StompException
     */
    public function get_nack_frame(Frame $frame, $transaction_id = null, $requeue = null)
    {
        if ($this->get_version() === Version::VERSION_1_0) {
            throw new Stomp_Exception('Stomp Version 1.0 has no support for NACK Frames.');
        }
        $nack = $this->create_frame('NACK');
        if ($requeue !== null) {
            $nack->add_headers(['requeue' => $requeue ? 'true' : 'false']);
        }
        $nack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            $nack['id'] = $frame->get_message_id();
        } else {
            $nack['message-id'] = $frame->get_message_id();
            if ($this->has_version(Version::VERSION_1_1)) {
                $nack['subscription'] = $frame['subscription'];
            }
        }
        $nack['message-id'] = $frame->get_message_id();
        return $nack;
    }
}