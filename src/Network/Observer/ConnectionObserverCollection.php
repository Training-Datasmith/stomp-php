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
 * ConnectionObserverCollection a collection of connection observers.
 *
 * @package Stomp\Network\Observer
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Connection_Observer_Collection implements Connection_Observer
{
    /**
     * @var ConnectionObserver[]
     */
    private $observers = [];
    /**
     * Adds new observers to the collection.
     *
     * @return ConnectionObserverCollection this collection
     */
    public function add_observer(Connection_Observer $observer): self
    {
        if (!in_array($observer, $this->observers, true)) {
            $this->observers[] = $observer;
        }
        return $this;
    }
    /**
     * Removes the observers from the collection.
     *
     * @return ConnectionObserverCollection this collection
     */
    public function remove_observer(Connection_Observer $observer): self
    {
        $index = array_search($observer, $this->observers, true);
        if ($index !== false) {
            unset($this->observers[$index]);
        }
        return $this;
    }
    /**
     * Returns the observers inside this collection.
     *
     * @return ConnectionObserver[]
     */
    public function get_observers(): array
    {
        return array_values($this->observers);
    }
    /**
     * Indicates that during a read call no frame was received, but an EOL line.
     */
    public function empty_line_received(): void
    {
        foreach ($this->observers as $item) {
            $item->empty_line_received();
        }
    }
    /**
     * Indicates that a frame has been received.
     *
     * @param Frame $frame that has been received
     */
    public function received_frame(Frame $frame): void
    {
        foreach ($this->observers as $item) {
            $item->received_frame($frame);
        }
    }
    /**
     * Indicates that a frame has been transmitted.
     */
    public function sent_frame(Frame $frame): void
    {
        foreach ($this->observers as $item) {
            $item->sent_frame($frame);
        }
    }
    /**
     * Indicates that the connection has no pending data.
     */
    public function empty_buffer(): void
    {
        foreach ($this->observers as $item) {
            $item->empty_buffer();
        }
    }
    /**
     * Indicates that the connection tried to read signaled data, but no data was returned.
     */
    public function empty_read(): void
    {
        foreach ($this->observers as $item) {
            $item->empty_read();
        }
    }
}