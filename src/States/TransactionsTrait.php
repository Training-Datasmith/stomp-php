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
use Stomp\Transport\Message;
use Stomp\Util\Id_Generator;
/**
 * TransactionsTrait provides base logic for all transaction based states.
 *
 * @package Stomp\States
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
trait Transactions_Trait
{
    /**
     * @return Protocol
     */
    abstract public function get_protocol();
    /**
     * @return Client
     */
    abstract public function get_client();
    /**
     * Id used for current transaction.
     *
     * @var int|string
     */
    protected $transaction_id;
    /**
     * Init the transaction state.
     */
    protected function init_transaction(array $options = [])
    {
        if (!isset($options['transactionId'])) {
            $this->transaction_id = Id_Generator::generate_id();
            $this->get_client()->send_frame($this->get_protocol()->get_begin_frame($this->transaction_id));
        } else {
            $this->transaction_id = $options['transactionId'];
        }
    }
    /**
     * Options for this transaction state.
     */
    protected function get_options(): array
    {
        return ['transactionId' => $this->transaction_id];
    }
    /**
     * Send a message within this transaction.
     *
     * @param string $destination
     * @return bool
     */
    public function send($destination, Message $message)
    {
        return $this->get_client()->send($destination, $message, ['transaction' => $this->transaction_id], false);
    }
    /**
     * Commit current transaction.
     */
    protected function transaction_commit()
    {
        $this->get_client()->send_frame($this->get_protocol()->get_commit_frame($this->transaction_id));
        Id_Generator::release_id($this->transaction_id);
    }
    /**
     * Abort the current transaction.
     */
    protected function transaction_abort()
    {
        $this->get_client()->send_frame($this->get_protocol()->get_abort_frame($this->transaction_id));
        Id_Generator::release_id($this->transaction_id);
    }
}