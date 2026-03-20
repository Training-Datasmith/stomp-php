<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Apollo\Mode;

use Stomp\Client;
/**
 * SequenceQueueBrowser ApolloMq util to browse a queue sequence based without removing messages from it.
 *
 * @see http://activemq.apache.org/apollo/documentation/stomp-manual.html
 *      #Using_Queue_Browsers_to_Implement_Durable_Topic_Subscriptions
 *
 * @package Stomp\Broker\Apollo\Mode
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Sequence_Queue_Browser extends Queue_Browser
{
    /**
     * Browser start at head at current queue
     */
    public const START_HEAD = 0;
    /**
     * Browser start at for new messages in queue
     */
    public const START_NEW = -1;
    /**
     * @var int
     */
    private $start_at;
    /**
     * Current offset.
     *
     * @var null|int
     */
    private $seq;
    /**
     * SequenceQueueBrowser constructor.
     *
     * @param string $destination
     * @param int $startAt
     * @param bool $stopOnEnd
     */
    public function __construct(Client $client, $destination, $start_at = self::START_HEAD, $stop_on_end = true)
    {
        $this->start_at = $start_at;
        parent::__construct($client, $destination, $stop_on_end);
    }
    /**
     * @inheritdoc
     */
    protected function get_header()
    {
        return parent::get_header() + ['include-seq' => 'seq', 'from-seq' => $this->start_at];
    }
    /**
     * @inheritdoc
     */
    public function read()
    {
        if ($frame = parent::read()) {
            $this->seq = $frame['seq'];
        }
        return $frame;
    }
    /**
     * Returns the last received sequence.
     *
     * @return null|int
     */
    public function get_seq()
    {
        return $this->seq;
    }
}