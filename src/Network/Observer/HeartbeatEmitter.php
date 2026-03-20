<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Network\Observer;

use Stomp\Exception\Connection_Exception;
use Stomp\Network\Connection;
use Stomp\Network\Observer\Exception\Heartbeat_Exception;
use Stomp\Transport\Frame;
/**
 * HeartbeatEmitter a very basic heartbeat emitter that sends beats from client side.
 *
 * Use this if you can guarantee that your client processes workloads within a given interval. This allows the server to
 * detect that your client is down when it fails sending heartbeats, your client will also fail with exception when the
 * server is not longer receiving heartbeats.
 *
 * If your client needs a unknown runtime to process Messages you should check ServerAliveObserver.
 *
 * @example $client->setHeartbeat(2000, 0); // indicate that we would send beats within a 2 second interval
 *          $emitter = new HeartbeatEmitter($client->getConnection());
 *          $client->getConnection()->getObservers()->addObserver($emitter);
 *
 * @see ServerAliveObserver
 * @package Stomp\Network\Observer\Heartbeat
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Heartbeat_Emitter extends Abstract_Beats
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * Defines the percentage amount of the calculated interval that will be used without emitting a beat.
     *
     * @var float
     */
    private $interval_usage;
    /**
     * Enables the pessimistic mode of the emitter, causing alive messages when we receive nothing from the socket.
     *
     * @var bool
     */
    private $pessimistic = false;
    /**
     * Emitter constructor.
     *
     * What is the interval usage?
     * The usage (percentage) defines the amount of agreed beat interval time,
     * that is allowed to pass before the emitter will send a beat.
     *
     * A higher value increases the risk that a beat is send after a timeout has occurred.
     * A lower value increases the beats and adds overhead to the connection.
     *
     * @param float $intervalUsage
     */
    public function __construct(Connection $connection, $interval_usage = 0.65)
    {
        $this->interval_usage = max(0.05, min($interval_usage, 0.95));
        $this->connection = $connection;
    }
    /**
     * Enables the pessimistic mode.
     *
     * @param bool $pessimistic
     */
    public function set_pessimistic($pessimistic): void
    {
        $this->pessimistic = $pessimistic;
    }
    /**
     * Called whenever the server send data.
     *
     * @return void
     */
    protected function on_server_activity()
    {
        $this->check_delayed();
    }
    /**
     * A frame with heartbeat details was detected.
     *
     * Class should set client or server interval.
     *
     * @return void
     */
    protected function on_heartbeat_frame(Frame $frame, array $beats)
    {
        if ($frame->get_command() === self::FRAME_SERVER_CONNECTED) {
            $this->interval_server = $beats[1];
            if ($this->interval_client === null) {
                $this->interval_client = $this->interval_server;
            }
        } else {
            $this->interval_client = $beats[0];
            $this->remember_activity();
        }
    }
    /**
     * Must return the interval (ms) that should be used to detect a delay.
     *
     * @param integer $maximum
     * @return float
     */
    protected function calculate_interval($maximum)
    {
        $interval_used = $maximum * $this->interval_usage;
        $this->assert_read_timeout_sufficient($interval_used);
        return $interval_used;
    }
    /**
     * Verify that the client configured heartbeats don't conflict with the connection read timeout.
     *
     * @param float $interval
     */
    private function assert_read_timeout_sufficient($interval): void
    {
        $read_timeout = $this->connection->get_read_timeout();
        $read_timeout_ms = $read_timeout[0] * 1000 + $read_timeout[1] / 1000;
        if ($interval < $read_timeout_ms) {
            throw new Heartbeat_Exception('Client heartbeat is lower than connection read timeout, causing failing heartbeats.');
        }
    }
    /**
     * Called whenever a activity is detected that was issued by the client.
     *
     * @return void
     */
    protected function on_client_activity()
    {
        $this->remember_activity();
    }
    /**
     * @inheritdoc
     */
    protected function on_potential_connection_state_activity()
    {
        if ($this->pessimistic && $this->is_enabled()) {
            $this->on_delay();
        } else {
            $this->check_delayed();
        }
    }
    /**
     * Send a beat to the server.
     *
     * @return void
     */
    protected function on_delay()
    {
        try {
            $this->connection->send_alive($this->interval_client / 1000);
        } catch (Connection_Exception $e) {
            throw new Heartbeat_Exception('Could not send heartbeat to server.', $e);
        }
        $this->remember_activity();
    }
}