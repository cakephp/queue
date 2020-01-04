<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Queue\Queue;

use Cake\Event\EventDispatcherTrait;
use Cake\Log\LogTrait;
use Exception;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Queue\Job\Message;

class Processor implements InteropProcessor
{
    use EventDispatcherTrait;
    use LogTrait;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Processor constructor
     *
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * The method processes messages
     *
     * @param \Interop\Queue\Message $queueMessage Message.
     * @param \Interop\Queue\Context $context Context.
     *
     * @return string|object with __toString method implemented
     */
    public function process(QueueMessage $queueMessage, Context $context)
    {
        $this->dispatchEvent('Processor.message.seen', ['queueMessage' => $queueMessage]);

        $message = new Message($queueMessage, $context);
        if (!is_callable($message->getCallable())) {
            $this->logger->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchEvent('Processor.message.invalid', ['message' => $message]);

            return InteropProcessor::REJECT;
        }

        $this->dispatchEvent('Processor.message.start', ['message' => $message]);

        try {
            $response = $this->processMessage($message);
        } catch (Exception $e) {
            $this->logger->debug(sprintf('Message encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $message,
                'exception' => $e,
            ]);

            return InteropProcessor::REQUEUE;
        }

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed sucessfully');
            $this->dispatchEvent('Processor.message.success', ['message' => $message]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection');
            $this->dispatchEvent('Processor.message.reject', ['message' => $message]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing');
        $this->dispatchEvent('Processor.message.failure', ['message' => $message]);

        return InteropProcessor::REQUEUE;
    }

    /**
     * @param \Queue\Job\Message $message Message.
     * @return string|object with __toString method implemented
     */
    public function processMessage(Message $message)
    {
        $callable = $message->getCallable();

        $response = InteropProcessor::REQUEUE;
        if (is_array($callable) && count($callable) == 2) {
            $className = $callable[0];
            $methodName = $callable[1];
            $instance = new $className();
            $response = $instance->$methodName($message);
        } elseif (is_string($callable)) {
            /** @psalm-suppress InvalidArgument */
            $response = call_user_func($callable, $message);
        }

        if ($response === null) {
            $response = InteropProcessor::ACK;
        }

        return $response;
    }
}
