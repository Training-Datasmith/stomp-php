<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Network;

use Stomp\Exception\Connection_Exception;
use Stomp\Exception\Error_Frame_Exception;
use Stomp\Network\Observer\Connection_Observer_Collection;
use Stomp\Transport\Frame;
use Stomp\Transport\Parser;
/**
 * A Stomp Connection
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Connection
{
    /**
     * Default ActiveMq port
     */
    public const DEFAULT_PORT = 61613;
    /**
     * Host schemes.
     *
     * @var array[]
     */
    private $hosts = [];
    /**
     * Connection timeout in seconds.
     *
     * @var integer
     */
    private $connect_timeout;
    /**
     * Timeout (seconds) that are applied on write calls.
     *
     * @var integer
     */
    private $write_timeout = 3;
    /**
     * Using persistent connection for creating socket
     *
     * @var bool
     */
    private $persistent_connection = false;
    /**
     * Connection read wait timeout.
     *
     * 0 => seconds
     * 1 => milliseconds
     *
     * @var array
     */
    private $read_timeout = [60, 0];
    /**
     * Connection options.
     *
     * @var array
     */
    private $params = ['randomize' => false];
    /**
     * Active connection resource.
     *
     * @var resource|null
     */
    private $connection;
    /**
     * Connected host info.
     *
     * @var array
     */
    private $active_host = [];
    /**
     * Stream Context used for client connection
     *
     * @see http://php.net/manual/de/function.stream-context-create.php
     *
     * @var array
     */
    private $context = [];
    /**
     * Frame parser
     *
     * @var Parser
     */
    private $parser;
    /**
     * Host connected to.
     *
     * @var String
     */
    private $host;
    /**
     * @var ConnectionObserverCollection
     */
    private $observers;
    /**
     * Maximum number of bytes to write to a resource.
     *
     * @var int
     */
    private $max_write_bytes = 8192;
    /**
     * Maximum number of bytes to read from a resource.
     *
     * @var int
     */
    private $max_read_bytes = 8192;
    /**
     * Alive Signal
     */
    public const ALIVE = "\n";
    /**
     * @var callable|null
     */
    private $wait_callback;
    /**
     * Initialize connection
     *
     * Example broker uri
     * - Use only one broker uri: tcp://localhost:61614
     * - use failover in given order: failover://(tcp://localhost:61614,ssl://localhost:61612)
     * - use brokers in random order: failover://(tcp://localhost:61614,ssl://localhost:61612)?randomize=true
     *
     * @param string $brokerUri
     * @param integer $connectionTimeout in seconds
     * @param array $context stream context
     * @throws ConnectionException
     */
    public function __construct($broker_uri, $connection_timeout = 1, array $context = [])
    {
        $this->parser = new Parser();
        $this->observers = new Connection_Observer_Collection();
        $this->parser->set_observer($this->observers);
        $this->connect_timeout = $connection_timeout;
        $this->context = $context;
        $pattern = "|^(([a-zA-Z0-9]+)://)+\\(*([a-zA-Z0-9\\.:/i,-_]+)\\)*\\??([a-zA-Z0-9=&]*)\$|i";
        if (preg_match($pattern, $broker_uri, $matches)) {
            $scheme = $matches[2];
            $hosts = $matches[3];
            $options = $matches[4];
            if ($options) {
                parse_str($options, $connection_options);
                $this->params = array_intersect_key($connection_options, $this->params) + $this->params;
            }
            if ($scheme != 'failover') {
                $this->parse_url($broker_uri);
            } else {
                $urls = explode(',', $hosts);
                foreach ($urls as $url) {
                    $this->parse_url($url);
                }
            }
        }
        if (empty($this->hosts)) {
            throw new Connection_Exception("Bad Broker URL {$broker_uri}. Check used scheme!");
        }
    }
    /**
     * Sets a wait callback that will be invoked when the connection is waiting for new data.
     *
     * This is a good place to call `pcntl_signal_dispatch()` if you need to ensure that your process signals in an
     * interval that is lower as read timeout. You should also return `false` in your callback if you don't want the
     * connection to continue waiting for data.
     *
     * @param callable|null $waitCallback
     */
    public function set_wait_callback($wait_callback): void
    {
        if ($wait_callback !== null) {
            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (!is_callable($wait_callback)) {
                throw new \InvalidArgumentException('$waitCallback must be callable.');
            }
        }
        $this->wait_callback = $wait_callback;
    }
    /**
     * Returns the connect timeout in seconds.
     *
     * @return int
     */
    public function get_connect_timeout()
    {
        return $this->connect_timeout;
    }
    /**
     * Returns the collection of observers of this connection.
     *
     * @return ConnectionObserverCollection
     */
    public function get_observers()
    {
        return $this->observers;
    }
    /**
     * Parse a broker URL
     *
     * @param string $url Broker URL
     * @throws ConnectionException
     */
    private function parse_url(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new Connection_Exception('Unable to parse url ' . $url);
        }
        array_push($this->hosts, $parsed + ['port' => '61613', 'scheme' => 'tcp']);
    }
    /**
     * Set the read timeout
     *
     * @param integer $seconds      seconds
     * @param integer $microseconds microseconds (1μs = 0.000001s, ex. 500ms = 500000)
     */
    public function set_read_timeout($seconds, $microseconds = 0): void
    {
        $this->read_timeout[0] = $seconds;
        $this->read_timeout[1] = $microseconds;
    }
    /**
     * Returns the read timeout
     *
     * First element contains full seconds, second the microseconds part.
     *
     * @return array
     */
    public function get_read_timeout()
    {
        return $this->read_timeout;
    }
    /**
     * Set the write timeout
     *
     * @param int $writeTimeout seconds
     */
    public function set_write_timeout($write_timeout): void
    {
        $this->write_timeout = $write_timeout;
    }
    /**
     * Set socket context
     */
    public function set_context(array $context): void
    {
        $this->context = $context;
    }
    /**
     * Set the maximum number of bytes to write to a resource
     *
     * This will be useful if you are suffering problems with OpenSSL or Amazon MQ
     *
     * @param int $maxWriteBytes bytes
     */
    public function set_max_write_bytes($max_write_bytes): void
    {
        $this->max_write_bytes = $max_write_bytes;
    }
    /**
     * Set the maximum number of bytes to read from a resource
     *
     * This will be useful if you are suffering problems with OpenSSL or Amazon MQ
     *
     * @param int $maxReadBytes bytes
     */
    public function set_max_read_bytes($max_read_bytes): void
    {
        $this->max_read_bytes = $max_read_bytes;
    }
    /**
     * Connect to an broker.
     *
     * @throws ConnectionException
     */
    public function connect(): bool
    {
        if (!$this->is_connected()) {
            $this->connection = $this->get_connection();
        }
        return true;
    }
    /**
     * @param boolean $persistentConnection
     */
    public function set_persistent_connection($persistent_connection): void
    {
        $this->persistent_connection = $persistent_connection;
    }
    /**
     * Get a connection.
     *
     * @return resource (stream)
     * @throws ConnectionException
     */
    protected function get_connection()
    {
        $hosts = $this->get_host_list();
        $last_exception = null;
        while ($host = array_shift($hosts)) {
            try {
                return $this->connect_socket($host);
            } catch (Connection_Exception $connection_exception) {
                $last_exception = $connection_exception;
            }
        }
        throw new Connection_Exception('Could not connect to a broker', [], $last_exception);
    }
    /**
     * Get the host list.
     */
    protected function get_host_list(): array
    {
        $hosts = array_values($this->hosts);
        if ($this->should_randomize_hosts()) {
            shuffle($hosts);
        }
        return $hosts;
    }
    /**
     * Returns whether the broker host list should be shuffled in random order.
     *
     * This applies when specifying multiple hosts using a failover:// protocol in the URI.
     *
     * @return bool
     *   Whether the broker hosts should be shuffled in random order.
     */
    protected function should_randomize_hosts(): bool
    {
        return filter_var($this->params['randomize'], FILTER_VALIDATE_BOOLEAN);
    }
    /**
     * Try to connect to given host.
     *
     * @return resource (stream)
     * @throws ConnectionException if connection setup fails
     */
    protected function connect_socket(array $host)
    {
        $this->active_host = $host;
        $err_no = null;
        $err_str = null;
        $context = stream_context_create($this->context);
        $flags = STREAM_CLIENT_CONNECT;
        if ($this->persistent_connection) {
            $flags |= STREAM_CLIENT_PERSISTENT;
        }
        $socket = @stream_socket_client($host['scheme'] . '://' . $host['host'] . ':' . $host['port'], $err_no, $err_str, $this->connect_timeout, $flags, $context);
        if (!is_resource($socket)) {
            throw new Connection_Exception(sprintf('Failed to connect. (%s: %s)', $err_no, $err_str), $host);
        }
        if (!@stream_set_blocking($socket, false)) {
            throw new Connection_Exception('Failed to set non blocking mode for stream.', $host);
        }
        $this->host = $host['host'];
        return $socket;
    }
    /**
     * Connection established.
     */
    public function is_connected(): bool
    {
        return $this->connection && is_resource($this->connection);
    }
    /**
     * Close connection.
     */
    public function disconnect(): void
    {
        if ($this->is_connected()) {
            @stream_socket_shutdown($this->connection, STREAM_SHUT_RDWR);
        }
        $this->connection = null;
        $this->active_host = [];
    }
    /**
     * Write frame to server.
     *
     * @throws ConnectionException
     */
    public function write_frame(Frame $stomp_frame): bool
    {
        if (!$this->is_connected()) {
            throw new Connection_Exception('Not connected to any server.', $this->active_host);
        }
        $this->write_data($stomp_frame, $this->write_timeout);
        $this->observers->sent_frame($stomp_frame);
        return true;
    }
    /**
     * Write passed data to the stream, respecting passed timeout.
     *
     * @param Frame|string $stompFrame
     * @param float $timeout in seconds, supporting fractions
     * @throws ConnectionException
     */
    private function write_data($stomp_frame, $timeout): void
    {
        $data = (string) $stomp_frame;
        $offset = 0;
        $size = strlen($data);
        $last_byte_time = microtime(true);
        do {
            $written = @fwrite($this->connection, substr($data, $offset), $this->max_write_bytes);
            if ($written === false) {
                throw new Connection_Exception('Was not possible to write frame!', $this->active_host);
            }
            if ($written > 0) {
                // offset tracking
                $offset += $written;
                $last_byte_time = microtime(true);
            } else if (microtime(true) - $last_byte_time > $timeout) {
                throw new Connection_Exception('Was not possible to write frame! Write operation timed out.', $this->active_host);
            }
            // keep some time to breath
            if ($written < $size) {
                time_nanosleep(0, 2500000);
                // 2.5ms / 0.0025s
            }
        } while ($offset < $size);
    }
    /**
     * Try to read a frame from the server.
     *
     * @return Frame|false when no frame to read
     * @throws ConnectionException
     * @throws ErrorFrameException
     */
    public function read_frame()
    {
        // first we try to check the parser for any leftover frames
        if ($frame = $this->parser->next_frame()) {
            return $this->on_frame($frame);
        }
        while ($this->has_data_to_read()) {
            $read = @fread($this->connection, $this->max_read_bytes);
            if ($read === false) {
                throw new Connection_Exception(sprintf('Was not possible to read data from stream.'), $this->active_host);
            }
            // this can be caused by different events on the stream, ex. new data or any kind of signal
            // it also happens when a ssl socket was closed on the other side... so we need to test
            if ($read === '') {
                $this->observers->empty_read();
                // again we give some time here
                // as this path is most likely indicating that the socket is not working anymore
                time_nanosleep(0, 5000000);
                // 5ms / 0.005s
                return false;
            }
            $this->parser->add_data($read);
            if ($frame = $this->parser->next_frame()) {
                return $this->on_frame($frame);
            }
        }
        return false;
    }
    /**
     * The connection onFrame handler.
     *
     * @throws ErrorFrameException
     */
    private function on_frame(Frame $frame): Frame
    {
        if ($frame->is_error_frame()) {
            throw new Error_Frame_Exception($frame);
        }
        return $frame;
    }
    /**
     * Check if connection has new data which can be read.
     *
     * This might wait until readTimeout is reached.
     *
     * @return boolean
     * @throws ConnectionException
     * @see Connection::setReadTimeout()
     */
    public function has_data_to_read()
    {
        if (!$this->is_connected()) {
            throw new Connection_Exception('Not connected to any server.', $this->active_host);
        }
        $is_data_in_buffer = $this->connection_has_data_to_read($this->read_timeout[0], $this->read_timeout[1]);
        if (!$is_data_in_buffer) {
            $this->observers->empty_buffer();
        }
        return $is_data_in_buffer;
    }
    /**
     * See if the connection has data left.
     *
     * If both timeout-parameters are set to 0, it will return immediately.
     *
     * @param int $timeoutSec Second-timeout part
     * @param int $timeoutMicros Microsecond-timeout part
     * @return bool
     * @throws ConnectionException
     */
    private function connection_has_data_to_read($timeout_sec, $timeout_micros)
    {
        $timeout = microtime(true) + $timeout_sec + ($timeout_micros ? $timeout_micros / 1000000 : 0);
        while (($has_data = $this->is_data_on_stream()) === false) {
            if ($timeout < microtime(true)) {
                return false;
            }
            if ($this->wait_callback) {
                if (call_user_func($this->wait_callback) === false) {
                    return false;
                }
            }
            $slept = time_nanosleep(0, 2500000);
            // 2.5ms / 0.0025s
            if (\is_array($slept)) {
                return false;
            }
        }
        return $has_data === true;
    }
    /**
     * Checks if there is readable data on the stream.
     *
     * Will return true if data is available, false if no data is detected and null if the operation was interrupted.
     *
     * @throws ConnectionException
     */
    private function is_data_on_stream(): ?bool
    {
        $read = [$this->connection];
        $write = null;
        $except = null;
        $has_stream_info = @stream_select($read, $write, $except, 0);
        if ($has_stream_info === false) {
            // can return `false` if used in combination with `pcntl_signal` and lead to false errors here
            $error = error_get_last();
            if ($error && stripos($error['message'], 'interrupted system call') === false) {
                throw new Connection_Exception('Check failed to determine if the socket is readable.', $this->active_host);
            }
            return null;
        }
        return $has_stream_info > 0;
    }
    /**
     * Returns the parser which is used by the connection.
     *
     * @return Parser
     */
    public function get_parser()
    {
        return $this->parser;
    }
    /**
     * Returns the host the connection was established to.
     *
     * @return String
     */
    public function get_host()
    {
        return $this->host;
    }
    /**
     * Writes an "alive" message on the connection to indicate that the client is alive.
     *
     * @param float $timeout in seconds supporting fractions (microseconds)
     *
     * @throws ConnectionException
     */
    public function send_alive($timeout = 1.0): void
    {
        if ($this->is_connected()) {
            $this->write_data(self::ALIVE, $timeout);
        }
    }
    /**
     * Immediately releases all allocated resources when the connection object gets destroyed.
     *
     * This is especially important for long running processes.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}