<?php

namespace Queue\Queue;

use Cake\Utility\Hash;
use Interop\Queue\Processor;
use JsonSerializable;

class JobData implements JsonSerializable
{
    protected $message;

    protected $parsedBody;

    public function __construct($message)
    {
        $this->message = $message;
        $this->parsedBody = json_decode($message->getBody(), true);

        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getCallable()
    {
        return Hash::get($this->parsedBody, 'class', null);
    }

    public function getData($key = null, $default = null)
    {
        if ($key === null) {
            return $this->parsedBody['args'][0];
        }

        return Hash::get($this->parsedBody['args'][0], $key, $default);
    }

    public function __toString()
    {
        return json_encode($this);
    }

    public function jsonSerialize()
    {
        return $this->parsedBody;
    }
}
