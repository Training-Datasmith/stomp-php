<?php

declare(strict_types=1);

/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stomp\Transport;

use ArrayAccess;

/**
 * Stomp Frames are messages that are sent and received on a stomp connection.
 *
 * @package Stomp
 */
class Frame implements ArrayAccess
{
    /**
     * Stomp Command
     *
     * @var string
     */
    protected $command;

    /**
     * Frame Headers
     *
     * @var array
     */
    protected $headers;

    /**
     * Frame Content
     *
     * @var mixed
     */
    public $body;

    /**
     * Frame should set an content-length header on transmission
     *
     * @var bool
     */
    private $addLengthHeader = false;

    /**
     * Frame is in stomp 1.0 mode
     *
     * @var bool
     */
    private $legacyMode = false;

    /**
     * Constructor
     *
     * @param string $command
     * @param string $body
     */
    public function __construct($command = null, array $headers = [], $body = null)
    {
        $this->command = $command;
        $this->headers = $headers ?: [];
        $this->body = $body;
    }

    /**
     * Add given headers to currently set headers.
     *
     * Will override existing keys.
     */
    public function addHeaders(array $header): self
    {
        $this->headers += $header;
        return $this;
    }

    /**
     * Stomp message Id
     *
     * @return string
     */
    public function getMessageId()
    {
        return $this['message-id'];
    }

    /**
     * Is error frame.
     */
    public function isErrorFrame(): bool
    {
        return ($this->command == 'ERROR');
    }

    /**
     * Tell the frame that we expect an length header.
     *
     * @param bool|false $expected
     */
    public function expectLengthHeader($expected = false): void
    {
        $this->addLengthHeader = $expected;
    }

    /**
     * Enable legacy mode for this frame
     *
     * @param bool|false $legacy
     */
    public function legacyMode($legacy = false): void
    {
        $this->legacyMode = $legacy;
    }

    /**
     * Frame is in legacy mode.
     *
     * @return bool
     */
    public function isLegacyMode()
    {
        return $this->legacyMode;
    }

    /**
     * Command
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Convert frame to transportable string
     */
    public function __toString(): string
    {
        $data = $this->command . "\n";

        if (!$this->legacyMode) {
            if ($this->body && ($this->addLengthHeader || stripos($this->body, "\x00") !== false)) {
                $this['content-length'] = $this->getBodySize();
            }
        }

        foreach ($this->headers as $name => $value) {
            $data .= $this->encodeHeaderValue($name) . ':' . $this->encodeHeaderValue($value) . "\n";
        }

        $data .= "\n";
        $data .= $this->body;
        return $data . "\x00";
    }

    /**
     * Size of Frame body.
     */
    protected function getBodySize(): int
    {
        return strlen($this->body);
    }

    /**
     * Encodes header values.
     *
     * @param string $value
     */
    protected function encodeHeaderValue($value): string
    {
        if ($this->legacyMode) {
            return str_replace(["\n"], ['\n'], $value);
        }
        return str_replace(['\\', "\r", "\n", ':'], ['\\\\', '\r', '\n', '\c'], $value);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->headers[$offset]);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->headers[$offset] ?? null;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if ($value !== null) {
            $this->headers[$offset] = $value;
        }
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->headers[$offset]);
    }
}
