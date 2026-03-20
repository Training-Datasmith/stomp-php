<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp;

use Stomp\Exception\Stomp_Exception;
use Stomp\Protocol\Protocol;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
/**
 * Simple Stomp Client
 *
 * This is a legacy implementation of the old Stomp Client (Version 2-3).
 * It's an almost stateless client, only wrapping some protocol calls for you.
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Simple_Stomp
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * LegacyStomp constructor.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    /**
     * Read response frame from server
     *
     * @return Frame|false when no frame to read
     */
    public function read()
    {
        return $this->client->read_frame();
    }
    /**
     * Register to listen to a given destination
     *
     * @param string $destination Destination queue
     * @param string $ack
     * @param string $selector
     * @return bool
     */
    public function subscribe($destination, $subscription_id = null, $ack = 'auto', $selector = null, array $header = [])
    {
        return $this->client->send_frame($this->get_protocol()->get_subscribe_frame($destination, $subscription_id, $ack, $selector)->add_headers($header));
    }
    /**
     * @return Protocol
     */
    protected function get_protocol()
    {
        return $this->client->get_protocol();
    }
    /**
     * Send a message
     *
     * @param string $destination
     * @return bool
     * @throws StompException
     */
    public function send($destination, Message $message)
    {
        return $this->client->send($destination, $message);
    }
    /**
     * Remove an existing subscription
     *
     * @param string $destination
     * @param string $subscriptionId
     * @return boolean
     * @throws StompException
     */
    public function unsubscribe($destination, $subscription_id = null, array $header = [])
    {
        return $this->client->send_frame($this->get_protocol()->get_unsubscribe_frame($destination, $subscription_id)->add_headers($header));
    }
    /**
     * Start a transaction
     *
     * @param string $transactionId
     * @return boolean
     * @throws StompException
     */
    public function begin($transaction_id = null)
    {
        return $this->client->send_frame($this->get_protocol()->get_begin_frame($transaction_id));
    }
    /**
     * Commit a transaction in progress
     *
     * @param string $transactionId
     * @return boolean
     * @throws StompException
     */
    public function commit($transaction_id = null)
    {
        return $this->client->send_frame($this->get_protocol()->get_commit_frame($transaction_id));
    }
    /**
     * Roll back a transaction in progress
     *
     * @param string $transactionId
     * @return bool
     */
    public function abort($transaction_id = null)
    {
        return $this->client->send_frame($this->get_protocol()->get_abort_frame($transaction_id));
    }
    /**
     * Acknowledge consumption of a message from a subscription
     */
    public function ack(Frame $frame): void
    {
        $this->client->send_frame($this->get_protocol()->get_ack_frame($frame), false);
    }
    /**
     * Not acknowledge consumption of a message from a subscription
     */
    public function nack(Frame $frame): void
    {
        $this->client->send_frame($this->get_protocol()->get_nack_frame($frame), false);
    }
}