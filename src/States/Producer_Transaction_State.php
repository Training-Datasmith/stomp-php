<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States;

use Stomp\States\Exception\Invalid_State_Exception;
/**
 * ProducerTransactionState client is working in an transaction scope as a message producer.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Producer_Transaction_State extends Producer_State
{
    use Transactions_Trait;
    /**
     * @inheritdoc
     */
    protected function init(array $options = [])
    {
        $this->init_transaction($options);
        /** @phpstan-ignore-next-line staticMethod.resultUnused */
        parent::init($options);
    }
    /**
     * @inheritdoc
     */
    public function commit(): void
    {
        $this->transaction_commit();
        $this->set_state(new Producer_State($this->get_client(), $this->get_base()), parent::get_options());
    }
    /**
     * @inheritdoc
     */
    public function abort(): void
    {
        $this->transaction_abort();
        $this->set_state(new Producer_State($this->get_client(), $this->get_base()), parent::get_options());
    }
    /**
     * @inheritdoc
     */
    public function subscribe($destination, $selector, $ack, array $header = [])
    {
        return $this->set_state(new Consumer_Transaction_State($this->get_client(), $this->get_base()), $this->get_options() + ['destination' => $destination, 'selector' => $selector, 'ack' => $ack, 'header' => $header]);
    }
    /**
     * @inheritdoc
     */
    public function begin()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
}