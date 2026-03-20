<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States\Meta;

use Stomp\Transport\Frame;
/**
 * Subscription Meta info
 *
 * @package Stomp\States\Meta
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Subscription
{
    /**
     * @var int
     */
    private $subscription_id;
    /**
     * @var String
     */
    private $selector;
    /**
     * @var String
     */
    private $destination;
    /**
     * @var String
     */
    private $ack;
    /**
     * @var array
     */
    private $header;
    /**
     * Subscription constructor.
     * @param String $destination
     * @param String $selector
     * @param String $ack
     * @param int $subscriptionId
     * @param array $header additionally passed to create this subscription
     */
    public function __construct($destination, $selector, $ack, $subscription_id, array $header = [])
    {
        $this->subscription_id = $subscription_id;
        $this->selector = $selector;
        $this->destination = $destination;
        $this->ack = $ack;
        $this->header = $header;
    }
    /**
     * @return int
     */
    public function get_subscription_id()
    {
        return $this->subscription_id;
    }
    /**
     * @return String
     */
    public function get_selector()
    {
        return $this->selector;
    }
    /**
     * @return String
     */
    public function get_destination()
    {
        return $this->destination;
    }
    /**
     * @return String
     */
    public function get_ack()
    {
        return $this->ack;
    }
    /**
     * @return array
     */
    public function get_header()
    {
        return $this->header;
    }
    /**
     * Checks if the given frame belongs to current Subscription.
     */
    public function belongs_to(Frame $frame): bool
    {
        return $frame['subscription'] == $this->subscription_id;
    }
}