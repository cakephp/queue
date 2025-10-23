<?php
declare(strict_types=1);

namespace Cake\Queue\Test\test_app\src\Queue;

use Cake\Core\ContainerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\Message;
use Enqueue\Consumption\Result;
use Error;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Test Custom Processor
 *
 * A custom processor for testing the configurable processor functionality.
 * Implements Interop\Queue\Processor to provide custom message processing behavior.
 */
class TestCustomProcessor implements InteropProcessor
{
    use EventDispatcherTrait;

    protected LoggerInterface $logger;

    protected ?ContainerInterface $container = null;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->container = $container;
    }

    /**
     * Process a message from the queue
     *
     * @param \Interop\Queue\Message $message The queue message
     * @param \Interop\Queue\Context $context The queue context
     * @return string|object Processing result
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->logger->debug('TestCustomProcessor processing message');

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
        } catch (Throwable $throwable) {
            $message->setProperty('jobException', $throwable);

            $this->logger->debug(sprintf('Message encountered exception: %s', $throwable->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $throwable,
            ]);

            return Result::requeue('Exception occurred while processing message');
        }

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed successfully by TestCustomProcessor');
            $this->dispatchEvent('Processor.message.success', ['message' => $jobMessage]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection by TestCustomProcessor');
            $this->dispatchEvent('Processor.message.reject', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing by TestCustomProcessor');
        $this->dispatchEvent('Processor.message.failure', ['message' => $jobMessage]);

        return InteropProcessor::REQUEUE;
    }

    /**
     * Process the job message and return the result
     *
     * @param \Cake\Queue\Job\Message $message The job message
     * @return string|object Processing result
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
