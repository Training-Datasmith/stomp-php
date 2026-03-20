<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Exception;

use Stomp\Transport\Frame;
/**
 * Exception that occurs, when a frame / response was received that was not expected at this moment.
 *
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Unexpected_Response_Exception extends Stomp_Exception
{
    /**
     *
     * @var Frame
     */
    private $frame;
    /**
     *
     * @param string $expectedInfo
     */
    public function __construct(Frame $frame, $expected_info)
    {
        $this->frame = $frame;
        parent::__construct(sprintf('Unexpected response received. %s', $expected_info));
    }
    /**
     *
     * @return Frame
     */
    public function get_frame()
    {
        return $this->frame;
    }
}