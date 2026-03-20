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
 * Stomp server send us an error frame.
 *
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Error_Frame_Exception extends Stomp_Exception
{
    /**
     *
     * @var Frame
     */
    private $frame;
    public function __construct(Frame $frame)
    {
        $this->frame = $frame;
        parent::__construct(sprintf('Error "%s"', $frame['message']));
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