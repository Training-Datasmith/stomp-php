<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Network\Observer;

use Stomp\Transport\Frame;
/**
 * AbstractBeats base for heart beat observer.
 *
 * @package Stomp\Network\Observer
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
abstract class Abstract_Beats implements Connection_Observer
{
    /**
     * Frame from client that request a connection.
     */
    public const FRAME_CLIENT_CONNECT = 'CONNECT';
    /**
     * Frame from server when a connection is established.
     */
    public const FRAME_SERVER_CONNECTED = 'CONNECTED';
    /**
     * The beat interval that the client wants to use.
     *
     * @var integer
     */
    protected $interval_client;
    /**
     * The beat interval that the server wants to use.
     *
     * @var integer
     */
    protected $interval_server;
    /**
     * The timestamp that is known as the last time a beat was detected/issued.
     *
     * @var float
     */
    private $lastbeat;
    /**
     * The interval (seconds with microseconds as fraction) that will be used to detect a delay.
     *
     * @var float
     */
    private $interval_used;
    /**
     * Whenever the emitter is configured to send beats.
     *
     * @var bool
     */
    private $enabled = false;
    /**
     * A frame with heartbeat details was detected.
     *
     * Child class should set client or server interval property.
     *
     * @see $intervalClient
     * @see $intervalServer
     *
     * @return void
     */
    abstract protected function on_heartbeat_frame(Frame $frame, array $beats);
    /**
     * Called whenever the server send data.
     *
     * @return void
     */
    abstract protected function on_server_activity();
    /**
     * Must return the interval (ms) that should be used to detect a delay.
     *
     * @param integer $maximum agreement from client and server in milliseconds
     * @return float
     */
    abstract protected function calculate_interval($maximum);
    /**
     * Called whenever a activity is detected that was issued by the client.
     *
     * @return void
     */
    abstract protected function on_client_activity();
    /**
     * Something on the connection state could have changed.
     *
     * @return void
     */
    abstract protected function on_potential_connection_state_activity();
    /**
     * Delay was detected.
     *
     * @return void
     */
    abstract protected function on_delay();
    /**
     * Checks if the emitter was enabled.
     *
     * @return bool
     */
    public function is_enabled()
    {
        return $this->enabled;
    }
    /**
     * Returns if the emitter is in a state that indicates a delay.
     *
     * @return bool
     */
    public function is_delayed()
    {
        if ($this->enabled && $this->lastbeat) {
            $now = microtime(true);
            return $now - $this->lastbeat > $this->interval_used;
        }
        return false;
    }
    /**
     * Returns the calculated interval for beats in seconds (with micro fraction).
     *
     * @return null|float
     */
    public function get_interval()
    {
        return $this->interval_used;
    }
    /**
     * Check if there is a delay and issue follow up tasks if so.
     *
     * @return void
     */
    protected function check_delayed()
    {
        if ($this->is_delayed()) {
            $this->on_delay();
        }
    }
    /**
     * Outgoing activity event.
     *
     * @return void
     */
    protected function remember_activity()
    {
        $this->lastbeat = microtime(true);
    }
    /**
     * Returns the heartbeat header.
     */
    private function get_heartbeats(Frame $frame): array
    {
        $beats = $frame['heart-beat'];
        if ($beats) {
            return explode(',', $beats, 2);
        }
        return [0, 0];
    }
    /**
     * Enables the delay detection when preconditions are fulfilled.
     */
    private function enable(Frame $frame): void
    {
        $this->on_heartbeat_frame($frame, $this->get_heartbeats($frame));
        if ($this->interval_server && $this->interval_client) {
            $interval_agreement = $this->calculate_interval(max($this->interval_client, $this->interval_server));
            $this->interval_used = $interval_agreement / 1000;
            // milli to micro
            if ($interval_agreement) {
                $this->enabled = true;
                $this->remember_activity();
            }
        }
    }
    /**
     * @inheritdoc
     */
    public function received_frame(Frame $frame): void
    {
        if ($this->enabled) {
            $this->on_server_activity();
            return;
        }
        if ($frame->get_command() === self::FRAME_SERVER_CONNECTED) {
            $this->enable($frame);
        }
    }
    /**
     * @inheritdoc
     */
    public function sent_frame(Frame $frame): void
    {
        if ($this->enabled) {
            $this->on_client_activity();
            return;
        }
        if ($frame->get_command() === self::FRAME_CLIENT_CONNECT) {
            $this->enable($frame);
        }
    }
    /**
     * @inheritdoc
     */
    public function empty_line_received(): void
    {
        $this->on_server_activity();
    }
    /**
     * @inheritdoc
     */
    public function empty_read(): void
    {
        $this->on_potential_connection_state_activity();
    }
    /**
     * @inheritdoc
     */
    public function empty_buffer(): void
    {
        $this->on_potential_connection_state_activity();
    }
}