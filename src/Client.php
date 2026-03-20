<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp;

use Stomp\Exception\Connection_Exception;
use Stomp\Exception\Missing_Receipt_Exception;
use Stomp\Exception\Stomp_Exception;
use Stomp\Exception\Unexpected_Response_Exception;
use Stomp\Network\Connection;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Frame;
/**
 * Stomp Client
 *
 * This is the minimal implementation of a Stomp Client, it allows to send and receive Frames using the Stomp Protocol.
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Client
{
    /**
     * Perform request synchronously
     *
     * @var boolean
     */
    private $sync = true;
    /**
     * Client id used for durable subscriptions
     *
     * @var string
     */
    private $client_id;
    /**
     * Connection session id
     *
     * @var string|null
     */
    private $session_id;
    /**
     * Frames that have been read but not processed yet.
     *
     * @var Frame[]
     */
    private $unprocessed_frames = [];
    /**
     * @var Connection|null
     */
    private $connection;
    /**
     *
     * @var Protocol|null
     */
    private $protocol;
    /**
     * Seconds to wait for a receipt.
     *
     * @var float
     */
    private $receipt_wait = 2;
    /**
     *
     * @var string
     */
    private $login;
    /**
     *
     * @var string
     */
    private $passcode;
    /**
     *
     * @var array
     */
    private $versions = [Version::VERSION_1_0, Version::VERSION_1_1, Version::VERSION_1_2];
    /**
     *
     * @var string
     */
    private $host;
    /**
     *
     * @var int[]
     */
    private $heartbeat = [0, 0];
    /**
     * @var bool
     */
    private $is_connecting = false;
    /**
     * Constructor
     *
     * @param string|Connection $broker Broker URL or a connection
     * @see Connection::__construct()
     */
    public function __construct($broker)
    {
        $this->connection = $broker instanceof Connection ? $broker : new Connection($broker);
    }
    /**
     * Configure versions to support.
     *
     * @param array $versions defaults to all client supported versions
     */
    public function set_versions(array $versions): void
    {
        $this->versions = $versions;
    }
    /**
     * Configure the login to use.
     *
     * @param string $login
     * @param string $passcode
     */
    public function set_login($login, $passcode): void
    {
        $this->login = $login;
        $this->passcode = $passcode;
    }
    /**
     * Sets an fixed vhostname, which will be passed on connect as header['host'].
     *
     * (null = Default value is the hostname determined by connection.)
     *
     * @param string $host
     */
    public function set_vhostname($host = null): void
    {
        $this->host = $host;
    }
    /**
     * Set the desired heartbeat for the connection.
     *
     * A heartbeat is a specific message that will be send / received when no other data is send / received
     * within an interval - to indicate that the connection is still stable. If client and server agree on a beat and
     * the interval passes without any data activity / beats the connection will be considered as broken and closed.
     *
     * If you want to make sure that the server is still available, you should use the ServerAliveObserver
     * in combination with an requested server heartbeat interval.
     *
     * If you define a heartbeat for client side, you must assure that
     * your application will send data within the interval.
     * You can add \Stomp\Network\Observer\HeartbeatEmitter to your connection in order to send beats automatically.
     *
     * If you don't use HeartbeatEmitter you must either send messages within the interval
     * or make calls to Connection::sendAlive()
     *
     * @param int $send
     *   Number of milliseconds between expected sending of heartbeats. 0 means
     *   no heartbeats sent.
     * @param int $receive
     *   Number of milliseconds between expected receipt of heartbeats. 0 means
     *   no heartbeats expected. (not yet supported by this client)
     * @see \Stomp\Network\Observer\ServerAliveObserver
     * @see \Stomp\Network\Observer\HeartbeatEmitter
     * @see \Stomp\Network\Connection::sendAlive()
     */
    public function set_heartbeat($send = 0, $receive = 0): void
    {
        $this->heartbeat = [$send, $receive];
    }
    /**
     * Connect to server
     *
     * @throws StompException
     * @see setVhostname
     */
    public function connect(): bool
    {
        if ($this->is_connected()) {
            return true;
        }
        $this->is_connecting = true;
        $this->connection->connect();
        $this->connection->get_parser()->legacy_mode(true);
        $this->protocol = new Protocol($this->client_id);
        $this->host = $this->host ?: $this->connection->get_host();
        $connect_frame = $this->protocol->get_connect_frame($this->login, $this->passcode, $this->versions, $this->host, $this->heartbeat);
        $this->send_frame($connect_frame, false);
        if ($frame = $this->get_connected_frame()) {
            $version = new Version($frame);
            if ($version->has_version(Version::VERSION_1_1)) {
                $this->connection->get_parser()->legacy_mode(false);
            }
            $this->session_id = $frame['session'];
            $this->protocol = $version->get_protocol($this->client_id);
            $this->is_connecting = false;
            return true;
        }
        throw new Connection_Exception('Connection not acknowledged');
    }
    /**
     * Returns the next available frame from the connection, respecting the connect timeout.
     *
     * @return null|Frame
     * @throws ConnectionException
     * @throws Exception\ErrorFrameException
     */
    private function get_connected_frame()
    {
        $deadline = microtime(true) + $this->get_connection()->get_connect_timeout();
        do {
            if ($frame = $this->connection->read_frame()) {
                return $frame;
            }
        } while (microtime(true) <= $deadline);
        return null;
    }
    /**
     * Send a message to a destination in the messaging system
     *
     * @param string $destination Destination queue
     * @param string|Frame $msg Message
     * @param boolean $sync Perform request synchronously
     * @return boolean
     */
    public function send($destination, $msg, array $header = [], $sync = null)
    {
        if (!$msg instanceof Frame) {
            return $this->send($destination, new Frame('SEND', $header, $msg), [], $sync);
        }
        $msg->add_headers($header);
        $msg['destination'] = $destination;
        return $this->send_frame($msg, $sync);
    }
    /**
     * Send a frame.
     *
     * @param boolean $sync
     * @return boolean
     */
    public function send_frame(Frame $frame, $sync = null)
    {
        if (!$this->is_connecting && !$this->is_connected()) {
            $this->connect();
        }
        // determine if client was configured to write sync or not
        $write_sync = $sync ?? $this->sync;
        if ($write_sync) {
            return $this->send_frame_expecting_receipt($frame);
        }
        return $this->connection->write_frame($frame);
    }
    /**
     * Write frame to server and expect an matching receipt frame
     *
     * @return bool
     */
    protected function send_frame_expecting_receipt(Frame $stomp_frame)
    {
        $receipt = md5(microtime());
        $stomp_frame['receipt'] = $receipt;
        $this->connection->write_frame($stomp_frame);
        return $this->wait_for_receipt($receipt);
    }
    /**
     * Wait for an receipt
     *
     * @param string $receipt
     * @throws UnexpectedResponseException If response has an invalid receipt.
     * @throws MissingReceiptException     If no receipt is received.
     */
    protected function wait_for_receipt($receipt): bool
    {
        $stop_after = $this->calculate_receipt_wait_end();
        while (true) {
            if ($frame = $this->connection->read_frame()) {
                if ($frame->get_command() == 'RECEIPT') {
                    if ($frame['receipt-id'] == $receipt) {
                        return true;
                    }
                    throw new Unexpected_Response_Exception($frame, sprintf('Expected receipt id %s', $receipt));
                }
                $this->unprocessed_frames[] = $frame;
            }
            if (microtime(true) >= $stop_after) {
                break;
            }
        }
        throw new Missing_Receipt_Exception($receipt);
    }
    /**
     * Returns the timestamp with micro time to stop wait for a receipt.
     *
     * @return float
     */
    protected function calculate_receipt_wait_end()
    {
        return microtime(true) + $this->receipt_wait;
    }
    /**
     * Read response frame from server
     *
     * @return Frame|false when no frame to read
     */
    public function read_frame()
    {
        return array_shift($this->unprocessed_frames) ?: $this->connection->read_frame();
    }
    /**
     * Graceful disconnect from the server
     * @param bool $sync
     */
    public function disconnect($sync = false): void
    {
        try {
            if ($this->connection && $this->connection->is_connected()) {
                if ($this->protocol) {
                    $this->send_frame($this->protocol->get_disconnect_frame(), $sync);
                }
            }
        } catch (Stomp_Exception $ex) {
            // nothing!
        }
        if ($this->connection) {
            $this->connection->disconnect();
        }
        $this->session_id = null;
        $this->unprocessed_frames = [];
        $this->protocol = null;
        $this->is_connecting = false;
    }
    /**
     * Current stomp session ID
     *
     * @return string|null
     */
    public function get_session_id()
    {
        return $this->session_id;
    }
    /**
     * Graceful object destruction
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }
    /**
     * Check if client session has ben established
     */
    public function is_connected(): bool
    {
        return !empty($this->session_id) && $this->connection->is_connected();
    }
    /**
     * Get the used connection.
     *
     * @return Connection
     */
    public function get_connection()
    {
        return $this->connection;
    }
    /**
     * Get the currently used protocol.
     *
     * @return null|\Stomp\Protocol\Protocol
     */
    public function get_protocol()
    {
        if (!$this->is_connecting && !$this->is_connected()) {
            $this->connect();
        }
        return $this->protocol;
    }
    /**
     * @return string
     */
    public function get_client_id()
    {
        return $this->client_id;
    }
    /**
     * @param string $clientId
     */
    public function set_client_id($client_id): self
    {
        $this->client_id = $client_id;
        return $this;
    }
    /**
     * Set seconds to wait for a receipt.
     *
     * @param float $seconds
     */
    public function set_receipt_wait($seconds): void
    {
        $this->receipt_wait = $seconds;
    }
    /**
     * Check if client runs in synchronized mode, which is the default operation mode.
     *
     * @return boolean
     */
    public function is_sync()
    {
        return $this->sync;
    }
    /**
     * Toggle synchronized mode.
     *
     * @param boolean $sync
     */
    public function set_sync($sync): void
    {
        $this->sync = $sync;
    }
}