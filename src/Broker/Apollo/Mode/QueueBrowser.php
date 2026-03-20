<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Apollo\Mode;

use Stomp\Broker\Apollo\Apollo;
use Stomp\Broker\Exception\Unsupported_Broker_Exception;
use Stomp\Client;
use Stomp\States\Meta\Subscription;
use Stomp\Util\Id_Generator;
/**
 * QueueBrowser ApolloMq util to browse a queue without removing messages from it.
 *
 * @see http://activemq.apache.org/apollo/documentation/stomp-manual.html#Browsing_Subscriptions
 *
 * @package Stomp\Broker\Apollo\Mode
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Queue_Browser
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var bool
     */
    private $stop_on_end;
    /**
     * @var Subscription
     */
    private $subscription;
    /**
     * @var bool
     */
    private $active = false;
    /**
     * @var bool
     */
    private $reached_end = false;
    /**
     * QueueBrowser constructor.
     *
     * @param string $destination
     * @param bool $stopOnEnd
     */
    public function __construct(Client $client, $destination, $stop_on_end = true)
    {
        $this->stop_on_end = $stop_on_end;
        $this->client = $client;
        $this->stop_on_end = $stop_on_end;
        $this->subscription = new Subscription($destination, null, 'auto', Id_Generator::generate_id());
    }
    /**
     * Protocol
     * @return Apollo
     * @throws UnsupportedBrokerException
     */
    final protected function get_protocol()
    {
        $protocol = $this->client->get_protocol();
        if (!$protocol instanceof Apollo) {
            throw new Unsupported_Broker_Exception($protocol, Apollo::class);
        }
        return $protocol;
    }
    /**
     * Headers used in subscribe.
     */
    protected function get_header(): array
    {
        return ['browser' => 'true', 'browser-end' => $this->stop_on_end ? 'true' : 'false'];
    }
    /**
     * Initialize subscription.
     */
    public function subscribe(): void
    {
        if (!$this->active) {
            $this->reached_end = false;
            $this->client->send_frame($this->get_protocol()->get_subscribe_frame($this->subscription->get_destination(), $this->subscription->get_subscription_id(), $this->subscription->get_ack(), $this->subscription->get_selector(), false)->add_headers($this->get_header()));
            $this->active = true;
        }
    }
    /**
     * End subscription.
     */
    public function unsubscribe(): void
    {
        if ($this->active) {
            $this->client->send_frame($this->get_protocol()->get_unsubscribe_frame($this->subscription->get_destination(), $this->subscription->get_subscription_id(), false));
            $this->active = false;
        }
    }
    /**
     * Read next message.
     *
     * @return bool|false|\Stomp\Transport\Frame
     */
    public function read()
    {
        if (!$this->active || $this->reached_end) {
            return false;
        }
        if ($frame = $this->client->read_frame()) {
            if ($this->stop_on_end && $frame['browser'] == 'end') {
                $this->reached_end = true;
                return false;
            }
        }
        return $frame;
    }
    /**
     * Last message was received (can only be true if 'StopAtEnd' is enabled!)
     *
     * @return boolean
     */
    public function has_reached_end()
    {
        return $this->reached_end;
    }
    /**
     * Subscription has been initialized.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->active;
    }
    /**
     * Subscription details.
     *
     * @return Subscription
     */
    public function get_subscription()
    {
        return $this->subscription;
    }
    /**
     * @inheritdoc
     */
    public function __destruct()
    {
        Id_Generator::release_id($this->subscription->get_subscription_id());
    }
}