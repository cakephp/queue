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
namespace Cake\Queue\Queue;

use Cake\Core\ContainerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\Message;
use Enqueue\Consumption\Result;
use Error;
use Exception;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class Processor implements InteropProcessor
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Cake\Queue\Queue\Processor>
     */
    use EventDispatcherTrait;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var \Cake\Core\ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    /**
     * Processor constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance.
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->container = $container;
    }

    /**
     * The method processes messages
     *
     * @param \Interop\Queue\Message $message Message.
     * @param \Interop\Queue\Context $context Context.
     * @return object|string with __toString method implemented
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->dispatchEvent('Processor.message.seen', ['queueMessage' => $message]);

        $jobMessage = new Message($message, $context, $this->container);
        try {
            $jobMessage->getCallable();
        } catch (RuntimeException | Error $e) {
            $this->logger->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchEvent('Processor.message.invalid', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $this->dispatchEvent('Processor.message.start', ['message' => $jobMessage]);

        try {
            $response = $this->processMessage($jobMessage);
        } catch (Exception $e) {
            $this->logger->debug(sprintf('Message encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $e,
            ]);

            return Result::requeue(sprintf('Exception occurred while processing message: %s', (string)$e));
        }

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed sucessfully');
            $this->dispatchEvent('Processor.message.success', ['message' => $jobMessage]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection');
            $this->dispatchEvent('Processor.message.reject', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing');
        $this->dispatchEvent('Processor.message.failure', ['message' => $jobMessage]);

        return InteropProcessor::REQUEUE;
    }

    /**
     * @param \Cake\Queue\Job\Message $message Message.
     * @return object|string with __toString method implemented
     */
    public function processMessage(Message $message): string|object
    {
        $callable = $message->getCallable();
        $response = $callable($message);
        if ($response === null) {
            $response = InteropProcessor::ACK;
        }

        return $response;
    }
}
