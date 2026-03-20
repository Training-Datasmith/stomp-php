<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp;

use Stomp\States\I_Stateful;
use Stomp\States\Meta\Subscription_List;
use Stomp\States\Producer_State;
use Stomp\States\State_Setter;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
/**
 * Stateful Stomp Client
 *
 * This is a stateful implementation of a stomp client.
 * This client will help you using stomp in a safe way by using the state machine pattern.
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Stateful_Stomp extends State_Setter implements I_Stateful
{
    /**
     * active state
     *
     * @var IStateful
     */
    private $state;
    /**
     * @var Client
     */
    private $client;
    /**
     * StatefulStomp constructor.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->state = new Producer_State($client, $this);
    }
    /**
     * Acknowledge consumption of a message from a subscription
     */
    public function ack(Frame $frame): void
    {
        $this->state->ack($frame);
    }
    /**
     * Not acknowledge consumption of a message from a subscription
     *
     * @param bool $requeue requeue header not supported in all brokers
     */
    public function nack(Frame $frame, $requeue = null): void
    {
        $this->state->nack($frame, $requeue);
    }
    /**
     * Send a message.
     *
     * @param string $destination
     * @return bool
     */
    public function send($destination, Message $message)
    {
        return $this->state->send($destination, $message);
    }
    /**
     * Begins an transaction.
     */
    public function begin(): void
    {
        $this->state->begin();
    }
    /**
     * Commit current transaction.
     */
    public function commit(): void
    {
        $this->state->commit();
    }
    /**
     * Abort current transaction.
     */
    public function abort(): void
    {
        $this->state->abort();
    }
    /**
     * Subscribe to given destination.
     *
     * Returns the subscriptionId used for this.
     *
     * @param string $destination
     * @param string $selector
     * @param string $ack
     * @return int
     */
    public function subscribe($destination, $selector = null, $ack = 'auto', array $header = [])
    {
        return $this->state->subscribe($destination, $selector, $ack, $header);
    }
    /**
     * Unsubscribe from current or given destination.
     *
     * @param int $subscriptionId
     */
    public function unsubscribe($subscription_id = null): void
    {
        $this->state->unsubscribe($subscription_id);
    }
    /**
     * Returns as list of all active subscriptions.
     *
     * @return SubscriptionList
     */
    public function get_subscriptions()
    {
        return $this->state->get_subscriptions();
    }
    /**
     * Read a frame
     *
     * @return \Stomp\Transport\Frame|false
     */
    public function read()
    {
        return $this->state->read();
    }
    /**
     * Current State
     *
     * @return IStateful
     */
    public function get_state()
    {
        return $this->state;
    }
    /**
     * Changes the current state.
     *
     * @return mixed
     */
    protected function set_state(I_Stateful $state)
    {
        $this->state = $state;
    }
    /**
     * Returns the used client.
     *
     * @return Client
     */
    public function get_client()
    {
        return $this->client;
    }
}