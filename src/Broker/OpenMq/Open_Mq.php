<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Open_Mq;

use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Frame;
/**
 * OpenMq Stomp dialect.
 *
 * @package Stomp
 * @author Markus Staab <maggus.staab@googlemail.com>
 */
class Open_Mq extends Protocol
{
    /**
     * @inheritdoc
     */
    public function get_ack_frame(Frame $frame, $transaction_id = null)
    {
        $ack = $this->create_frame('ACK');
        $ack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            if (isset($frame['ack'])) {
                $ack['id'] = $frame['ack'];
            } else {
                $ack['id'] = $frame->get_message_id();
            }
        } else {
            $ack['message-id'] = $frame->get_message_id();
        }
        // spec quote: "ACK should always specify a "subscription" header for the subscription id
        //              that the message to be acked was delivered to ."
        // see https://mq.java.net/4.4-content/stomp-funcspec.html
        $ack['subscription'] = $frame['subscription'];
        return $ack;
    }
}