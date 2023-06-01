<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Queue\Job\Message;
use Exception;
use Interop\Queue\Processor as InteropProcessor;

class TestProcessor
{
    public static Message $lastProcessMessage;

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param Message $message The message to process
     * @return null
     * @throws Exception
     */
    public static function processAndThrowException(Message $message)
    {
        throw new Exception('Something went wrong');
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Cake\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnAck(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::ACK;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Cake\Queue\Job\Message $message The message to process
     * @return null
     */
    public static function processReturnNull(Message $message)
    {
        static::$lastProcessMessage = $message;

        return null;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Cake\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnReject(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::REJECT;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Cake\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnRequeue(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::REQUEUE;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Cake\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnString(Message $message)
    {
        static::$lastProcessMessage = $message;

        return 'invalid value';
    }
}
