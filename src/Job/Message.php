<?php
declare(strict_types=1);

namespace Queue\Job;

use Cake\Utility\Hash;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use JsonSerializable;

class Message implements JsonSerializable
{
    /**
     * @var \Interop\Queue\Context
     */
    protected $context;

    /**
     * @var \Interop\Queue\Message
     */
    protected $originalMessage;

    /**
     * @var array
     */
    protected $parsedBody;

    /**
     * @param \Interop\Queue\Message $originalMessage Queue message.
     * @param \Interop\Queue\Context $context Context.
     */
    public function __construct(QueueMessage $originalMessage, Context $context)
    {
        $this->context = $context;
        $this->originalMessage = $originalMessage;
        $this->parsedBody = json_decode($originalMessage->getBody(), true);
    }

    /**
     * @return \Interop\Queue\Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return \Interop\Queue\Message
     */
    public function getOriginalMessage()
    {
        return $this->originalMessage;
    }

    /**
     * @return array
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @return mixed
     */
    public function getCallable()
    {
        return Hash::get($this->parsedBody, 'class', null);
    }

    /**
     * @param mixed $key Key
     * @param mixed $default Default value.
     * @return mixed
     */
    public function getArgument($key = null, $default = null)
    {
        if ($key === null) {
            return $this->parsedBody['args'][0];
        }

        return Hash::get($this->parsedBody['args'][0], $key, $default);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->parsedBody;
    }
}
