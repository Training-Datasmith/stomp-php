<?php
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stomp\Broker\ActiveMq;

use ArrayAccess;

/**
 * Options for ActiveMq Stomp
 *
 * For more details visit http://activemq.apache.org/stomp.html
 *
 * @package Stomp\Broker\ActiveMq
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Options implements ArrayAccess
{
    private $extensions = [
        'activemq.dispatchAsync',
        'activemq.exclusive',
        'activemq.maximumPendingMessageLimit',
        'activemq.noLocal',
        'activemq.prefetchSize',
        'activemq.priority',
        'activemq.retroactive',
    ];

    private $options = [];

    /**
     * Options constructor.
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            $this[$key] = $value;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->options[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->options[$offset];
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if (in_array($offset, $this->extensions, true)) {
            $this->options[$offset] = $value;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }

    public function getOptions()
    {
        return $this->options;
    }


    public function activateRetroactive(): self
    {
        $this['activemq.retroactive'] = 'true';
        return $this;
    }
    public function activateExclusive(): self
    {
        $this['activemq.exclusive'] = 'true';
        return $this;
    }

    public function activateDispatchAsync(): self
    {
        $this['activemq.dispatchAsync'] = 'true';
        return $this;
    }

    public function setPriority($priority): self
    {
        $this['activemq.priority'] = $priority;
        return $this;
    }


    public function setPrefetchSize($size): self
    {
        $this['activemq.prefetchSize'] = max($size, 1);
        return $this;
    }


    public function activateNoLocal(): self
    {
        $this['activemq.noLocal'] = 'true';
        return $this;
    }

    public function setMaximumPendingLimit($limit): self
    {
        $this['activemq.maximumPendingMessageLimit'] = $limit;
        return $this;
    }
}
