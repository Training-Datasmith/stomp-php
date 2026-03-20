<?php

declare (strict_types=1);
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
    private $add_length_header = false;
    /**
     * Frame is in stomp 1.0 mode
     *
     * @var bool
     */
    private $legacy_mode = false;
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
    public function add_headers(array $header): self
    {
        $this->headers += $header;
        return $this;
    }
    /**
     * Stomp message Id
     *
     * @return string
     */
    public function get_message_id()
    {
        return $this['message-id'];
    }
    /**
     * Is error frame.
     */
    public function is_error_frame(): bool
    {
        return $this->command == 'ERROR';
    }
    /**
     * Tell the frame that we expect an length header.
     *
     * @param bool|false $expected
     */
    public function expect_length_header($expected = false): void
    {
        $this->add_length_header = $expected;
    }
    /**
     * Enable legacy mode for this frame
     *
     * @param bool|false $legacy
     */
    public function legacy_mode($legacy = false): void
    {
        $this->legacy_mode = $legacy;
    }
    /**
     * Frame is in legacy mode.
     *
     * @return bool
     */
    public function is_legacy_mode()
    {
        return $this->legacy_mode;
    }
    /**
     * Command
     *
     * @return string
     */
    public function get_command()
    {
        return $this->command;
    }
    /**
     * Body
     *
     * @return string
     */
    public function get_body()
    {
        return $this->body;
    }
    /**
     * Headers
     *
     * @return array
     */
    public function get_headers()
    {
        return $this->headers;
    }
    /**
     * Convert frame to transportable string
     */
    public function __toString(): string
    {
        $data = $this->command . "\n";
        if (!$this->legacy_mode) {
            if ($this->body && ($this->add_length_header || stripos($this->body, "\x00") !== false)) {
                $this['content-length'] = $this->get_body_size();
            }
        }
        foreach ($this->headers as $name => $value) {
            $data .= $this->encode_header_value($name) . ':' . $this->encode_header_value($value) . "\n";
        }
        $data .= "\n";
        $data .= $this->body;
        return $data . "\x00";
    }
    /**
     * Size of Frame body.
     */
    protected function get_body_size(): int
    {
        return strlen($this->body);
    }
    /**
     * Encodes header values.
     *
     * @param string $value
     */
    protected function encode_header_value($value): string
    {
        if ($this->legacy_mode) {
            return str_replace(["\n"], ['\n'], $value);
        }
        return str_replace(['\\', "\r", "\n", ':'], ['\\\\', '\r', '\n', '\c'], $value);
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetExists($offset)
    {
        return isset($this->headers[$offset]);
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        return $this->headers[$offset] ?? null;
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $value): void
    {
        if ($value !== null) {
            $this->headers[$offset] = $value;
        }
    }
    /**
     * @inheritdoc
     */
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset): void
    {
        unset($this->headers[$offset]);
    }
}