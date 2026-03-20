<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States;

use Stomp\Client;
use Stomp\Protocol\Protocol;
use Stomp\Stateful_Stomp;
use Stomp\States\Exception\Invalid_State_Exception;
use Stomp\States\Meta\Subscription_List;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
/**
 * StateTemplate for StompStates.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
abstract class State_Template extends State_Setter implements I_Stateful
{
    /**
     * @var Client
     */
    private $client;
    /**
     * StateMachine
     *
     * @var StatefulStomp
     */
    private $base;
    /**
     * StateTemplate constructor.
     */
    public function __construct(Client $client, Stateful_Stomp $base)
    {
        $this->client = $client;
        $this->base = $base;
    }
    /**
     * Returns the base StateMachine.
     *
     * @return StatefulStomp
     */
    protected function get_base()
    {
        return $this->base;
    }
    /**
     * Activates the current state, after it has been applied on base.
     *
     * @return mixed
     */
    abstract protected function init(array $options = []);
    /**
     * Returns the options needed in current state.
     *
     * @return array
     */
    abstract protected function get_options();
    /**
     * @return Client
     */
    protected function get_client()
    {
        return $this->client;
    }
    /**
     * @return Protocol
     */
    protected function get_protocol()
    {
        return $this->client->get_protocol();
    }
    /**
     * @inheritdoc
     */
    protected function set_state(I_Stateful $state, array $options = [])
    {
        $init = null;
        if ($state instanceof State_Template) {
            $init = $state->init($options);
        }
        $this->base->set_state($state);
        return $init;
    }
    /**
     * @inheritdoc
     */
    public function ack(Frame $frame)
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function nack(Frame $frame, $requeue = null)
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
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
    public function begin()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function commit()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function abort()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function subscribe($destination, $selector, $ack, array $header = [])
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function unsubscribe($subscription_id = null)
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function read()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
    /**
     * @inheritdoc
     */
    public function get_subscriptions()
    {
        return new Subscription_List();
    }
}