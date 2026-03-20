<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Transport;

/**
 * FrameFactory defines how new frames are created after the frame details have been extracted.
 *
 * @package Stomp\Transport
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Frame_Factory
{
    /**
     * @var callable[]
     */
    private $resolver = [];
    /**
     * FrameFactory constructor.
     */
    public function __construct()
    {
        // register default additional builtin resolvers
        $this->resolver[] = function ($command, array $headers, $body) {
            if (isset($headers['transformation']) && strcasecmp($headers['transformation'], 'jms-map-json') == 0) {
                return new Map($body, $headers, $command);
            }
        };
    }
    /**
     * Creates a frame instance out of the given frame details.
     *
     * @param string $command
     * @param string $body
     * @param boolean $legacyMode stomp 1.0 mode (headers)
     * @return Frame
     */
    public function create_frame($command, array $headers, $body, $legacy_mode)
    {
        foreach ($this->resolver as $resolver) {
            if ($frame = $resolver($command, $headers, $body, $legacy_mode)) {
                return $frame;
            }
        }
        return $this->default_frame($command, $headers, $body, $legacy_mode);
    }
    /**
     * Creates a new default frame instance.
     *
     * @param string $command
     * @param string $body
     * @param boolean $legacyMode
     */
    private function default_frame($command, array $headers, $body, $legacy_mode): \Stomp\Transport\Frame
    {
        $frame = new Frame($command, $headers, $body);
        $frame->legacy_mode($legacy_mode);
        return $frame;
    }
    /**
     * Register a new resolver inside this frame factory.
     *
     * The new resolver becomes the first one which will be used to handle the frame create request.
     * The resolver must return null/false if he won't create a frame for the request.
     *
     * @param callable $callable
     */
    public function register_resolver($callable): self
    {
        array_unshift($this->resolver, $callable);
        return $this;
    }
}