<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Exception;

/**
 * Exception that occurs, when a frame receipt was not received.
 *
 *
 * @package Stomp
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Missing_Receipt_Exception extends Stomp_Exception
{
    /**
     * @var string
     */
    private $receipt_id;
    /**
     *
     * @param string $receiptId
     */
    public function __construct($receipt_id)
    {
        $this->receipt_id = $receipt_id;
        parent::__construct(sprintf('Missing receipt Frame for id "%s". Maybe the queue server is under heavy load. ' . 'Try to increase timeouts.', $receipt_id));
    }
    /**
     * Expected receipt id.
     *
     * @return String
     */
    public function get_receipt_id()
    {
        return $this->receipt_id;
    }
}