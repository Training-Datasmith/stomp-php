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
 * Message that contains a stream of uninterpreted bytes
 *
 * @package Stomp
 */
class Bytes extends Message
{
    /**
     * Constructor
     *
     * @param string $body
     */
    public function __construct($body, array $headers = [])
    {
        parent::__construct($body, $headers);
        $this->headers['content-type'] = 'application/octet-stream';
        $this->expect_length_header(true);
    }
    /**
     * @inheritdoc
     */
    protected function get_body_size(): int
    {
        return ini_get('mbstring.func_overload') ? mb_strlen($this->get_body(), '8bit') : strlen($this->get_body());
    }
}