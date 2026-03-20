<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Network\Observer;

use Stomp\Network\Observer\Exception\Heartbeat_Exception;
use Stomp\Transport\Frame;
/**
 * ServerAliveObserver an observer that checks for signals from server side.
 *
 * Use this to ensure that the server your listening to is still alive.
 *
 * If you want to signal the server that your client is still available check HeartbeatEmitter.
 *
 * @example $client->setHeartbeat(0, 2000); // indicate that we would receive server beats within a 2 second interval
 *          $client->getConnection()->getObservers()->addObserver(new ServerAliveObserver());
 *
 * @see HeartbeatEmitter
 * @package Stomp\Network\Observer
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Server_Alive_Observer extends Abstract_Beats
{
    /**
     * Defines the percentage amount of the calculated interval that will be used without emitting a beat.
     *
     * @var float
     */
    private $interval_usage;
    /**
     * Emitter constructor.
     *
     * What is the interval usage?
     * The usage (percentage) defines the amount of agreed beat interval time,
     * that is allowed to pass before the observer decides that the server is delayed. (not alive anymore)
     *
     * A higher value increases the risk that a dead server is not detected over a given period.
     * A lower value increases the risk that a server is declared as dead when not.
     *
     * @param float $intervalUsage 150% default
     */
    public function __construct($interval_usage = 1.5)
    {
        $this->interval_usage = max(1, $interval_usage);
    }
    /**
     * @inheritdoc
     */
    protected function on_potential_connection_state_activity()
    {
        $this->check_delayed();
    }
    /**
     * @inheritdoc
     */
    protected function on_server_activity()
    {
        $this->remember_activity();
    }
    /**
     * @inheritdoc
     */
    protected function on_client_activity()
    {
        // ignored here, as we see failures when the write fails
    }
    /**
     * @inheritdoc
     */
    protected function on_delay()
    {
        throw new Heartbeat_Exception('The server failed to send expected heartbeats.');
    }
    /**
     * @inheritdoc
     */
    protected function on_heartbeat_frame(Frame $frame, array $beats)
    {
        if ($frame->get_command() === self::FRAME_CLIENT_CONNECT) {
            $this->interval_client = $beats[1];
        } else {
            $this->interval_server = $beats[0];
            $this->remember_activity();
        }
    }
    /**
     * @inheritdoc
     */
    protected function calculate_interval($maximum)
    {
        return $maximum * $this->interval_usage;
    }
}