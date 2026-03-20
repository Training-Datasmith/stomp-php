<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States\Meta;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stomp\Transport\Frame;
/**
 * SubscriptionList meta info for active subscriptions.
 *
 * @package Stomp\States\Meta
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Subscription_List implements IteratorAggregate, ArrayAccess, Countable
{
    /**
     * @var Subscription[]
     */
    private $subscriptions = [];
    /**
     * Returns the last added active Subscription.
     *
     * @return Subscription
     */
    public function get_last()
    {
        return end($this->subscriptions);
    }
    /**
     * Returns the subscription the frame belongs to or false if no matching subscription was found.
     *
     * @return Subscription|false
     */
    public function get_subscription(Frame $frame)
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->belongs_to($frame)) {
                return $subscription;
            }
        }
        return false;
    }
    /**
     * @inheritdoc
     *
     * @return \Iterator|Subscription[]
     */
    #[\Return_Type_Will_Change]
    public function getIterator()
    {
        return new ArrayIterator($this->subscriptions);
    }
    /**
     * @inheritdoc
     *
     * @return bool
     */
    #[\Return_Type_Will_Change]
    public function offsetExists($offset)
    {
        return isset($this->subscriptions[$offset]);
    }
    /**
     * @inheritdoc
     *
     * @return Subscription
     */
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        return $this->subscriptions[$offset];
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $value): void
    {
        $this->subscriptions[$offset] = $value;
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset): void
    {
        unset($this->subscriptions[$offset]);
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function count()
    {
        return count($this->subscriptions);
    }
}