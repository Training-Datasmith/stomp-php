<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Protocol;

use Stomp\Exception\Stomp_Exception;
use Stomp\Transport\Frame;
/**
 * Stomp base protocol
 *
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Protocol
{
    /**
     * Client id used for durable subscriptions
     *
     * @var string
     */
    private $client_id;
    /**
     * @var string
     */
    private $version;
    /**
     * Server Version
     *
     * @var string
     */
    private $server;
    /**
     * Setup stomp protocol with configuration.
     *
     * @param string $clientId
     * @param string $version
     * @param string $server
     */
    public function __construct($client_id, $version = Version::VERSION_1_0, $server = null)
    {
        $this->client_id = $client_id;
        $this->server = $server;
        $this->version = $version;
    }
    /**
     * Get the connect frame
     *
     * @param string $login
     * @param string $passcode
     * @param string $host
     * @param int[] $heartbeat
     * @return \Stomp\Transport\Frame
     */
    final public function get_connect_frame($login = '', $passcode = '', array $versions = [], $host = null, array $heartbeat = [0, 0])
    {
        $frame = $this->create_frame('CONNECT');
        $frame->legacy_mode(true);
        if ($login || $passcode) {
            $frame->add_headers(['login' => $login, 'passcode' => $passcode]);
        }
        if ($this->has_client_id()) {
            $frame['client-id'] = $this->get_client_id();
        }
        if (!empty($versions)) {
            $frame['accept-version'] = implode(',', $versions);
        }
        $frame['host'] = $host;
        $frame['heart-beat'] = $heartbeat[0] . ',' . $heartbeat[1];
        return $frame;
    }
    /**
     * Get subscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @param string $ack
     * @param string $selector
     * @return \Stomp\Transport\Frame
     * @throws StompException
     */
    public function get_subscribe_frame($destination, $subscription_id = null, $ack = 'auto', $selector = null)
    {
        // validate ACK types per spec
        // https://stomp.github.io/stomp-specification-1.0.html#frame-ACK
        // https://stomp.github.io/stomp-specification-1.1.html#ACK
        // https://stomp.github.io/stomp-specification-1.2.html#ACK
        if ($this->has_version(Version::VERSION_1_1)) {
            $valid_acks = ['auto', 'client', 'client-individual'];
        } else {
            $valid_acks = ['auto', 'client'];
        }
        if (!in_array($ack, $valid_acks)) {
            throw new Stomp_Exception(sprintf('"%s" is not a valid ack value for STOMP %s. A valid value is one of %s', $ack, $this->version, implode(',', $valid_acks)));
        }
        $frame = $this->create_frame('SUBSCRIBE');
        $frame['destination'] = $destination;
        $frame['ack'] = $ack;
        $frame['id'] = $subscription_id;
        $frame['selector'] = $selector;
        return $frame;
    }
    /**
     * Get unsubscribe frame.
     *
     * @param string $destination
     * @param string $subscriptionId
     * @return \Stomp\Transport\Frame
     */
    public function get_unsubscribe_frame($destination, $subscription_id = null)
    {
        $frame = $this->create_frame('UNSUBSCRIBE');
        $frame['destination'] = $destination;
        $frame['id'] = $subscription_id;
        return $frame;
    }
    /**
     * Get transaction begin frame.
     *
     * @param string $transactionId
     * @return \Stomp\Transport\Frame
     */
    public function get_begin_frame($transaction_id = null)
    {
        $frame = $this->create_frame('BEGIN');
        $frame['transaction'] = $transaction_id;
        return $frame;
    }
    /**
     * Get transaction commit frame.
     *
     * @param string $transactionId
     * @return \Stomp\Transport\Frame
     */
    public function get_commit_frame($transaction_id = null)
    {
        $frame = $this->create_frame('COMMIT');
        $frame['transaction'] = $transaction_id;
        return $frame;
    }
    /**
     * Get transaction abort frame.
     *
     * @param string $transactionId
     * @return \Stomp\Transport\Frame
     */
    public function get_abort_frame($transaction_id = null)
    {
        $frame = $this->create_frame('ABORT');
        $frame['transaction'] = $transaction_id;
        return $frame;
    }
    /**
     * Get message acknowledge frame.
     *
     * @param string $transactionId
     * @return Frame
     */
    public function get_ack_frame(Frame $frame, $transaction_id = null)
    {
        $ack = $this->create_frame('ACK');
        $ack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            if (isset($frame['ack'])) {
                $ack['id'] = $frame['ack'];
            } else {
                $ack['id'] = $frame->get_message_id();
            }
        } else {
            $ack['message-id'] = $frame->get_message_id();
            if ($this->has_version(Version::VERSION_1_1)) {
                $ack['subscription'] = $frame['subscription'];
            }
        }
        return $ack;
    }
    /**
     * Get message not acknowledge frame.
     *
     * @param string $transactionId
     * @param bool $requeue Requeue header
     * @return \Stomp\Transport\Frame
     * @throws StompException
     * @throws \LogicException
     */
    public function get_nack_frame(Frame $frame, $transaction_id = null, $requeue = null)
    {
        if ($requeue !== null) {
            throw new \LogicException('requeue header not supported');
        }
        if ($this->version === Version::VERSION_1_0) {
            throw new Stomp_Exception('Stomp Version 1.0 has no support for NACK Frames.');
        }
        $nack = $this->create_frame('NACK');
        $nack['transaction'] = $transaction_id;
        if ($this->has_version(Version::VERSION_1_2)) {
            $nack['id'] = $frame->get_message_id();
        } else {
            $nack['message-id'] = $frame->get_message_id();
            if ($this->has_version(Version::VERSION_1_1)) {
                $nack['subscription'] = $frame['subscription'];
            }
        }
        $nack['message-id'] = $frame->get_message_id();
        return $nack;
    }
    /**
     * Get the disconnect frame.
     *
     * @return \Stomp\Transport\Frame
     */
    public function get_disconnect_frame()
    {
        $frame = $this->create_frame('DISCONNECT');
        if ($this->has_client_id()) {
            $frame['client-id'] = $this->get_client_id();
        }
        return $frame;
    }
    /**
     * Client Id is set
     */
    public function has_client_id(): bool
    {
        return (bool) $this->client_id;
    }
    /**
     * Client Id is set
     *
     * @return string
     */
    public function get_client_id()
    {
        return $this->client_id;
    }
    /**
     * Stomp Version
     *
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }
    /**
     * Server Version Info
     *
     * @return string
     */
    public function get_server()
    {
        return $this->server;
    }
    /**
     * Checks if given version is included (equal or lower) in active protocol version.
     *
     * @param string $version
     */
    public function has_version($version): bool
    {
        return version_compare($this->version, $version, '>=');
    }
    /**
     * Creates a Frame according to the detected STOMP version.
     *
     * @param string $command
     */
    protected function create_frame($command): \Stomp\Transport\Frame
    {
        $frame = new Frame($command);
        if ($this->version === Version::VERSION_1_0) {
            $frame->legacy_mode(true);
        }
        return $frame;
    }
}