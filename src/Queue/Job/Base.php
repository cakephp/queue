<?php

namespace Queue\Queue\Job;

use Cake\Utility\Hash;
use Interop\Queue\Processor;
use JsonSerializable;

class Base implements JsonSerializable
{
    protected $message;
    protected $item;

    public function __construct($message)
    {
        $this->message = $message;
        $this->item = json_decode($message->getBody(), true);

        return $this;
    }

    public function getCallable()
    {
        return Hash::get($this->item, 'class', null);
    }

    public function data($key = null, $default = null)
    {
        if ($key === null) {
            return $this->item['args'][0];
        }

        if (array_key_exists($key, $this->item['args'][0])) {
            return $this->item['args'][0][$key];
        }

        return $default;
    }

    public function acknowledge()
    {
        return Processor::ACK;
    }

    public function reject()
    {
        return Processor::REJECT;
    }

    public function item()
    {
        return $this->item;
    }

    public function __toString()
    {
        return json_encode($this);
    }

    public function jsonSerialize()
    {
        return $this->item;
    }
}
