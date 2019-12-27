<?php

namespace Queue\Queue;

use Cake\Utility\Hash;
use Interop\Queue\Context;
use Interop\Queue\Message;
use JsonSerializable;

class JobData implements JsonSerializable
{
    protected $context;

    protected $message;

    protected $parsedBody;

    public function __construct(Message $message, Context $context)
    {
        $this->context = $context;
        $this->message = $message;
        $this->parsedBody = json_decode($message->getBody(), true);

        return $this;
    }

    public function getContext()
    {
        return $this->context;
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

    public function getArgument($key = null, $default = null)
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
