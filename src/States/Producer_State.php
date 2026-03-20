<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States;

/**
 * ProducerState client is working as a message producer.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Producer_State extends State_Template
{
    /**
     * @inheritdoc
     */
    protected function init(array $options = [])
    {
        // nothing to do here
    }
    /**
     * @inheritdoc
     */
    public function begin(): void
    {
        $this->set_state(new Producer_Transaction_State($this->get_client(), $this->get_base()));
    }
    /**
     * @inheritdoc
     */
    public function subscribe($destination, $selector, $ack, array $header = [])
    {
        return $this->set_state(new Consumer_State($this->get_client(), $this->get_base()), ['destination' => $destination, 'selector' => $selector, 'ack' => $ack, 'header' => $header]);
    }
    /**
     * @inheritdoc
     */
    protected function get_options(): array
    {
        return [];
    }
}