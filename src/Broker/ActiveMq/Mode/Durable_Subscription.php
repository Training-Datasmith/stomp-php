<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Active_Mq\Mode;

use Stomp\Client;
use Stomp\Exception\Stomp_Exception;
use Stomp\States\Meta\Subscription;
use Stomp\Transport\Frame;
/**
 * DurableSubscription for ActiveMq.
 *
 * @package Stomp\Broker\ActiveMq\Mode
 * @author Jens Radtke <swefl.oss@fin-sn.de>l
 */
class Durable_Subscription extends Active_Mq_Mode
{
    /**
     * @var Subscription
     */
    private $subscription;
    /**
     * Subscription state
     *
     * @var bool
     */
    private $active = false;
    /**
     * DurableSubscription constructor.
     * @param string $topic
     * @param string $selector
     * @param string $ack
     * @param string $subscriptionId
     * @throws StompException
     */
    public function __construct(Client $client, $topic, $selector = null, $ack = 'auto', $subscription_id = null)
    {
        parent::__construct($client);
        if (!$client->get_client_id()) {
            throw new Stomp_Exception('Client must have been configured to use a specific clientId!');
        }
        $server = $client->get_protocol()->get_server();
        if (is_null($subscription_id) && substr($server, 0, 16) === 'ActiveMQ-Artemis') {
            throw new Stomp_Exception('Durable subscription requires a specific subscriptionId!');
        }
        $subscription_id = $subscription_id ?? $client->get_client_id();
        $this->subscription = new Subscription($topic, $selector, $ack, $subscription_id);
    }
    /**
     * Init the subscription.
     */
    public function activate(): void
    {
        if (!$this->active) {
            $this->client->send_frame($this->get_protocol()->get_subscribe_frame($this->subscription->get_destination(), $this->subscription->get_subscription_id(), $this->subscription->get_ack(), $this->subscription->get_selector(), true)->add_headers($this->options->get_options()));
            $this->active = true;
        }
    }
    /**
     * Mark durable subscription as offline.
     *
     * @see deactivate() if you want to indicate that the consumer is permanently removed.
     */
    public function inactive(): void
    {
        if ($this->active) {
            $this->client->send_frame($this->get_protocol()->get_unsubscribe_frame($this->subscription->get_destination(), $this->subscription->get_subscription_id()));
            $this->active = false;
        }
    }
    /**
     * Permanently remove durable subscription.
     *
     * @see inactive() if you just want to indicate that the consumer is offline now.
     */
    public function deactivate(): void
    {
        if ($this->active) {
            $this->inactive();
            $this->client->send_frame($this->get_protocol()->get_unsubscribe_frame($this->subscription->get_destination(), $this->subscription->get_subscription_id(), true));
            $this->active = false;
        }
    }
    /**
     * Reads a frame.
     *
     * @return false|\Stomp\Transport\Frame
     */
    public function read()
    {
        return $this->client->read_frame();
    }
    /**
     * Ack a frame.
     */
    public function ack(Frame $frame): void
    {
        $this->client->send_frame($this->get_protocol()->get_ack_frame($frame));
    }
    /**
     * Nack a frame.
     */
    public function nack(Frame $frame): void
    {
        $this->client->send_frame($this->get_protocol()->get_nack_frame($frame));
    }
    /**
     * Returns the Subscription details.
     *
     * @return Subscription
     */
    public function get_subscription()
    {
        return $this->subscription;
    }
    /**
     * Check if subscription is currently active.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->active;
    }
}