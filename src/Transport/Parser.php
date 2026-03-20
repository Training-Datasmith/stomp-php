<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Transport;

use Stomp\Network\Observer\Connection_Observer;
/**
 * A Stomp frame parser
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @author Michael Caplan <mcaplan@labnet.net>
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Parser
{
    /**
     * Frame end
     */
    public const FRAME_END = "\x00";
    /**
     * Frame that has been parsed last.
     *
     * @var Frame|null
     */
    private $frame;
    /**
     * Active Frame command
     *
     * @var string
     */
    private $command;
    /**
     * Active Frame headers
     *
     * @var array
     */
    private $headers = [];
    /**
     * Active Frame expected body size (content-length header)
     *
     * @var integer|null
     */
    private $expected_body_length;
    /**
     * Parser mode
     *
     * @var string
     */
    private $mode = self::MODE_HEADER;
    /**
     * Expecting header data mode
     */
    public const MODE_HEADER = 'HEADER';
    /**
     * Expecting body end marker mode
     */
    public const MODE_BODY = 'BODY';
    /**
     * Header end marker CR_LF
     */
    public const HEADER_STOP_CR_LF = "\r\n\r\n";
    /**
     * Header end marker LF
     */
    public const HEADER_STOP_LF = "\n\n";
    /**
     * Parser offset within buffer
     *
     * @var int
     */
    private $offset = 0;
    /**
     * Buffer size
     *
     * @var int
     */
    private $buffer_size;
    /**
     * Current buffer for new frames.
     *
     * @var string
     */
    private $buffer = '';
    /**
     * Parser is in stomp 1.0 mode
     *
     * @var bool
     */
    private $legacy_mode = false;
    /**
     * @var FrameFactory
     */
    private $factory;
    /**
     * @var ConnectionObserver|null
     */
    private $observer;
    /**
     * Parser constructor.
     *
     * @param FrameFactory $factory
     */
    public function __construct(?Frame_Factory $factory = null)
    {
        $this->factory = $factory ?: new Frame_Factory();
    }
    /**
     * Sets the observer for the parser, in order to receive heartbeat information.
     */
    public function set_observer(Connection_Observer $observer): self
    {
        $this->observer = $observer;
        return $this;
    }
    /**
     * Returns the factory that will be used to create frame instances.
     *
     * @return FrameFactory
     */
    public function get_factory()
    {
        return $this->factory;
    }
    /**
     * Set parser in legacy mode.
     *
     * @param bool|false $legacy
     */
    public function legacy_mode($legacy = false): void
    {
        $this->legacy_mode = $legacy;
    }
    /**
     * Add data to parse.
     */
    public function add_data(string $data): void
    {
        $this->buffer .= $data;
    }
    /**
     * Get next parsed frame.
     *
     * @deprecated Will be removed in next version. Please use nextFrame().
     * @return Frame
     */
    public function get_frame()
    {
        return $this->frame;
    }
    /**
     * Parse current buffer and return the next available frame, otherwise return null.
     *
     * @return null|Frame
     */
    public function next_frame()
    {
        if ($this->parse()) {
            $frame = $this->get_frame();
            $this->frame = null;
            if ($this->observer) {
                $this->observer->received_frame($frame);
            }
            return $frame;
        }
        return null;
    }
    /**
     * Parse current buffer for frames.
     *
     * @deprecated Will become private in next version. Please use nextFrame().
     *
     * @return bool
     */
    public function parse()
    {
        if ($this->buffer === '') {
            return false;
        }
        $this->frame = null;
        $this->offset = 0;
        $this->buffer_size = strlen($this->buffer);
        /** @phpstan-ignore-next-line */
        while ($this->offset < $this->buffer_size) {
            if ($this->mode === self::MODE_HEADER) {
                $this->skip_empty_lines();
                if ($this->detect_frame_head()) {
                    $this->mode = self::MODE_BODY;
                } else {
                    break;
                }
            }
            if ($this->detect_frame_end()) {
                $this->mode = self::MODE_HEADER;
            }
            break;
        }
        /** @phpstan-ignore-next-line */
        if ($this->offset > 0) {
            // remove parsed buffer
            $this->buffer = substr($this->buffer, $this->offset);
        }
        return $this->frame !== null;
    }
    /**
     * Skips empty lines before frame headers (they are allowed after \00)
     */
    private function skip_empty_lines(): void
    {
        $found_heartbeat = false;
        while ($this->offset < $this->buffer_size) {
            $char = substr($this->buffer, $this->offset, 1);
            if ($char === "\x00" || $char === "\n" || $char === "\r") {
                $this->offset++;
                $found_heartbeat = true;
            } else {
                break;
            }
        }
        if ($found_heartbeat && $this->observer) {
            $this->observer->empty_line_received();
        }
    }
    /**
     * Detect frame header end marker, starting from current offset.
     */
    private function detect_frame_head(): bool
    {
        $first_cr_lf = strpos($this->buffer, self::HEADER_STOP_CR_LF, $this->offset);
        $first_lf = strpos($this->buffer, self::HEADER_STOP_LF, $this->offset);
        // we need to use the first available marker, so we need to make sure that cr lf don't overrule lf
        if ($first_cr_lf !== false && ($first_lf === false || $first_lf > $first_cr_lf)) {
            $this->extract_frame_meta(substr($this->buffer, $this->offset, $first_cr_lf - $this->offset));
            $this->offset = $first_cr_lf + strlen(self::HEADER_STOP_CR_LF);
            return true;
        }
        if ($first_lf !== false) {
            $this->extract_frame_meta(substr($this->buffer, $this->offset, $first_lf - $this->offset));
            $this->offset = $first_lf + strlen(self::HEADER_STOP_LF);
            return true;
        }
        return false;
    }
    /**
     * Detect frame end marker, starting from current offset.
     */
    private function detect_frame_end(): bool
    {
        $body_size = null;
        if ($this->expected_body_length) {
            if ($this->buffer_size - $this->offset >= $this->expected_body_length) {
                $body_size = $this->expected_body_length;
            }
        } elseif (($frame_end = strpos($this->buffer, self::FRAME_END, $this->offset)) !== false) {
            $body_size = $frame_end - $this->offset;
        }
        if ($body_size !== null) {
            $this->set_frame($body_size);
            $this->offset += $body_size + strlen(self::FRAME_END);
            // x00
        }
        return $body_size !== null;
    }
    /**
     * Adds a frame from current known command, headers. Uses current offset and given body size.
     *
     * @param integer $bodySize
     */
    private function set_frame($body_size): void
    {
        $this->frame = $this->factory->create_frame($this->command, $this->headers, (string) substr($this->buffer, $this->offset, $body_size), $this->legacy_mode);
        $this->expected_body_length = null;
        $this->headers = [];
        $this->mode = self::MODE_HEADER;
    }
    /**
     * Extracts command and headers from given header source.
     *
     * @param string $source
     */
    private function extract_frame_meta($source): void
    {
        $headers = preg_split("/(\r?\n)+/", $source);
        $this->command = array_shift($headers);
        foreach ($headers as $header) {
            $header_details = explode(':', $header, 2);
            $name = $this->decode_header_value($header_details[0]);
            $value = isset($header_details[1]) ? $this->decode_header_value($header_details[1]) : true;
            if (!isset($this->headers[$name])) {
                $this->headers[$name] = $value;
            }
        }
        if (isset($this->headers['content-length'])) {
            $this->expected_body_length = (int) $this->headers['content-length'];
        }
    }
    /**
     * Decodes header values.
     */
    private function decode_header_value(string $value): string
    {
        if ($this->legacy_mode) {
            return str_replace(['\n'], ["\n"], $value);
        }
        return str_replace(['\r', '\n', '\c', '\\\\'], ["\r", "\n", ':', '\\'], $value);
    }
    /**
     * Resets the current buffer within this parser and returns the flushed buffer value.
     */
    public function flush_buffer(): string
    {
        $this->expected_body_length = null;
        $this->headers = [];
        $this->mode = self::MODE_HEADER;
        $current_buffer = substr($this->buffer, $this->offset);
        $this->offset = 0;
        $this->buffer_size = 0;
        $this->buffer = '';
        return $current_buffer;
    }
}