<?php
declare(strict_types=1);

namespace TestApp\Queue;

use Cake\Core\ContainerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\JobExecutionTrait;
use Cake\Queue\Job\Message;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Test Custom Processor
 *
 * A custom processor for testing the configurable processor functionality.
 * Implements Interop\Queue\Processor to provide custom message processing behavior.
 */
class TestCustomProcessor implements InteropProcessor
{
    use EventDispatcherTrait;
    use JobExecutionTrait;

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

        return $this->runJob($jobMessage);
    }
}
