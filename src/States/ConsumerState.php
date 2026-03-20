<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States;

use InvalidArgumentException;
use Stomp\States\Meta\Subscription;
use Stomp\States\Meta\Subscription_List;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
use Stomp\Util\Id_Generator;
/**
 * ConsumerState client acts as a consumer.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Consumer_State extends State_Template
{
    /**
     * Subscription ack mode
     *
     * @var string
     */
    protected $ack;
    /**
     * Subscription selector
     *
     * @var string
     */
    protected $selector;
    /**
     * Subscription target
     *
     * @var string
     */
    protected $destination;
    /**
     * SubscriptionId
     * @var int
     */
    protected $sub_id;
    /**
     * @var SubscriptionList
     */
    protected $subscriptions;
    /**
     * @inheritdoc
     */
    protected function init(array $options = [])
    {
        $this->subscriptions = new Subscription_List();
        if (isset($options['subscriptions'])) {
            $this->subscriptions = $options['subscriptions'];
        } else {
            $this->subscribe($options['destination'], $options['selector'], $options['ack'], $options['header']);
        }
        return $this->subscriptions->get_last()->get_subscription_id();
    }
    /**
     * @inheritdoc
     */
    public function ack(Frame $frame): void
    {
        $this->get_client()->send_frame($this->get_protocol()->get_ack_frame($frame), false);
    }
    /**
     * @inheritdoc
     */
    public function nack(Frame $frame, $requeue = null): void
    {
        $this->get_client()->send_frame($this->get_protocol()->get_nack_frame($frame, null, $requeue), false);
    }
    /**
     * @inheritdoc
     */
    public function send($destination, Message $message)
    {
        return $this->get_client()->send($destination, $message);
    }
    /**
     * @inheritdoc
     */
    public function begin(): void
    {
        $this->set_state(new Consumer_Transaction_State($this->get_client(), $this->get_base()), $this->get_options());
    }
    /**
     * @inheritdoc
     */
    public function subscribe($destination, $selector, $ack, array $header = [])
    {
        $subscription = new Subscription($destination, $selector, $ack, Id_Generator::generate_id(), $header);
        $this->get_client()->send_frame($this->get_protocol()->get_subscribe_frame($subscription->get_destination(), $subscription->get_subscription_id(), $subscription->get_ack(), $subscription->get_selector())->add_headers($header));
        $this->subscriptions[$subscription->get_subscription_id()] = $subscription;
        return $subscription->get_subscription_id();
    }
    /**
     * @inheritdoc
     */
    public function unsubscribe($subscription_id = null): void
    {
        if ($this->end_subscription($subscription_id)) {
            $this->set_state(new Producer_State($this->get_client(), $this->get_base()));
        }
    }
    /**
     * Closes given subscription or last opened.
     *
     * @param string $subscriptionId
     * @return bool true if last one was closed
     */
    protected function end_subscription($subscription_id = null): bool
    {
        if (!$subscription_id) {
            $subscription_id = $this->subscriptions->get_last()->get_subscription_id();
        }
        if (!isset($this->subscriptions[$subscription_id])) {
            throw new InvalidArgumentException(sprintf('%s is no active subscription!', $subscription_id));
        }
        $subscription = $this->subscriptions[$subscription_id];
        $this->get_client()->send_frame($this->get_protocol()->get_unsubscribe_frame($subscription->get_destination(), $subscription->get_subscription_id()));
        Id_Generator::release_id($subscription->get_subscription_id());
        unset($this->subscriptions[$subscription->get_subscription_id()]);
        if ($this->subscriptions->count() == 0) {
            return true;
        }
        return false;
    }
    /**
     * @inheritdoc
     */
    public function read()
    {
        return $this->get_client()->read_frame();
    }
    /**
     * @inheritdoc
     */
    public function get_subscriptions()
    {
        return $this->subscriptions;
    }
    /**
     * @inheritdoc
     */
    protected function get_options(): array
    {
        return ['subscriptions' => $this->subscriptions];
    }
}