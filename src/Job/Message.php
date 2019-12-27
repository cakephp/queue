<?php

namespace Queue\Queue;

use Cake\Utility\Hash;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use JsonSerializable;

class Message implements JsonSerializable
{
    protected $context;

    protected $originalMessage;

    protected $parsedBody;

    public function __construct(QueueMessage $originalMessage, Context $context)
    {
        $this->context = $context;
        $this->originalMessage = $originalMessage;
        $this->parsedBody = json_decode($originalMessage->getBody(), true);

        return $this;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getOriginalMessage()
    {
        return $this->originalMessage;
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
