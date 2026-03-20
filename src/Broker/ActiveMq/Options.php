<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Broker\Active_Mq;

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
    private $extensions = ['activemq.dispatchAsync', 'activemq.exclusive', 'activemq.maximumPendingMessageLimit', 'activemq.noLocal', 'activemq.prefetchSize', 'activemq.priority', 'activemq.retroactive'];
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
    #[\Return_Type_Will_Change]
    public function offsetExists($offset)
    {
        return isset($this->options[$offset]);
    }
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        return $this->options[$offset];
    }
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $value): void
    {
        if (in_array($offset, $this->extensions, true)) {
            $this->options[$offset] = $value;
        }
    }
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }
    public function get_options()
    {
        return $this->options;
    }
    public function activate_retroactive(): self
    {
        $this['activemq.retroactive'] = 'true';
        return $this;
    }
    public function activate_exclusive(): self
    {
        $this['activemq.exclusive'] = 'true';
        return $this;
    }
    public function activate_dispatch_async(): self
    {
        $this['activemq.dispatchAsync'] = 'true';
        return $this;
    }
    public function set_priority($priority): self
    {
        $this['activemq.priority'] = $priority;
        return $this;
    }
    public function set_prefetch_size($size): self
    {
        $this['activemq.prefetchSize'] = max($size, 1);
        return $this;
    }
    public function activate_no_local(): self
    {
        $this['activemq.noLocal'] = 'true';
        return $this;
    }
    public function set_maximum_pending_limit($limit): self
    {
        $this['activemq.maximumPendingMessageLimit'] = $limit;
        return $this;
    }
}