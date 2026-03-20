<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\States\Exception;

use Stomp\Exception\Stomp_Exception;
use Stomp\States\I_Stateful;
/**
 * InvalidStateException indicates that an call to an operation is not possible in current state.
 *
 * @package Stomp\States\Exception
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Invalid_State_Exception extends Stomp_Exception
{
    /**
     * InvalidStateException constructor.
     *
     * @param string $method
     */
    public function __construct(I_Stateful $state, $method)
    {
        parent::__construct(sprintf('"%s" is not allowed in "%s".', $method, get_class($state)));
    }
}