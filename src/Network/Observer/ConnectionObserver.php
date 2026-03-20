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
 * Interface ConnectionObserver defines the observable events on a connection.
 *
 * @package Stomp\Network\Observer
 */
interface Connection_Observer
{
    /**
     * Indicates that during a read call no frame was received, but an EOL line.
     *
     * @return void
     */
    public function empty_line_received();
    /**
     * Indicates that the connection has no pending data.
     *
     * @return void
     */
    public function empty_buffer();
    /**
     * Indicates that the connection tried to read signaled data, but no data was returned.
     *
     * @return void
     */
    public function empty_read();
    /**
     * Indicates that a frame has been received from the server.
     *
     * @param Frame $frame that has been received
     * @return void
     */
    public function received_frame(Frame $frame);
    /**
     * Indicates that a frame has been sent to the server.
     *
     * @return void
     */
    public function sent_frame(Frame $frame);
}