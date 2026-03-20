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
use Stomp\Transport\Frame;
/**
 * ConsumerTransactionState client is a consumer within an transaction.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Consumer_Transaction_State extends Consumer_State
{
    use Transactions_Trait;
    /**
     * @inheritdoc
     */
    protected function init(array $options = [])
    {
        $this->init_transaction($options);
        return parent::init($options);
    }
    /**
     * @inheritdoc
     */
    public function commit(): void
    {
        $this->get_client()->send_frame($this->get_protocol()->get_commit_frame($this->transaction_id));
        $this->set_state(new Consumer_State($this->get_client(), $this->get_base()), parent::get_options());
    }
    /**
     * @inheritdoc
     */
    public function abort(): void
    {
        $this->transaction_abort();
        $this->set_state(new Consumer_State($this->get_client(), $this->get_base()), parent::get_options());
    }
    /**
     * @inheritdoc
     */
    public function ack(Frame $frame): void
    {
        $this->get_client()->send_frame($this->get_protocol()->get_ack_frame($frame, $this->transaction_id), false);
    }
    /**
     * @inheritdoc
     */
    public function nack(Frame $frame, $requeue = null): void
    {
        $this->get_client()->send_frame($this->get_protocol()->get_nack_frame($frame, $this->transaction_id, $requeue), false);
    }
    /**
     * @inheritdoc
     */
    public function unsubscribe($subscription_id = null): void
    {
        if ($this->end_subscription($subscription_id)) {
            $this->set_state(new Producer_Transaction_State($this->get_client(), $this->get_base()), ['transactionId' => $this->transaction_id]);
        }
    }
    /**
     * @inheritdoc
     */
    public function begin()
    {
        throw new Invalid_State_Exception($this, __FUNCTION__);
    }
}