<?php
namespace Queue\Queue;

use Cake\Event\EventDispatcherTrait;
use Cake\Log\LogTrait;
use Enqueue\Client\Message;
use Enqueue\Consumption\Context\End;
use Enqueue\Consumption\Context\InitLogger;
use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\Context\MessageResult;
use Enqueue\Consumption\Context\PostConsume;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\Context\PreConsume;
use Enqueue\Consumption\Context\PreSubscribe;
use Enqueue\Consumption\Context\ProcessorException;
use Enqueue\Consumption\Context\Start;
use Enqueue\Consumption\ExtensionInterface;

// TODO: Figure out how to avoid needing to set a bunch of empty methods
class QueueExtension implements ExtensionInterface
{
    use EventDispatcherTrait;
    use LogTrait;

    protected $maxIterations;
    protected $maxRuntime;

    protected $iterations = 0;
    protected $runtime = 0;
    protected $started_at;

    public function __construct(int $maxIterations, int $maxRuntime)
    {
        $this->maxIterations = $maxIterations;
        $this->maxRuntime = $maxRuntime;
        $this->started_at = microtime(true);
    }

    /**
     * Executed at every new cycle before calling SubscriptionConsumer::consume method.
     * The consumption could be interrupted at this step.
     */
    public function onPreConsume(PreConsume $context): void
    {
        $this->runtime = microtime(true) - $this->started_at;
        if ($this->maxRuntime > 0 && $this->runtime >= $this->maxRuntime) {
            $this->log('Max runtime reached, exiting', LogLevel::DEBUG);
            $this->dispatchEvent('Processor.maxRuntime');
            $context->interruptExecution(0);
        } elseif ($this->maxIterations > 0 && $this->iterations >= $this->maxIterations) {
            $this->log('Max iterations reached, exiting', LogLevel::DEBUG);
            $this->dispatchEvent('Processor.maxIterations');
            $context->interruptExecution(0);
        }
    }

    /**
     * The method is called after SubscriptionConsumer::consume method exits.
     * The consumption could be interrupted at this point.
     */
    public function onPostConsume(PostConsume $context): void
    {
        $this->iterations++;
    }

    /**
     * The method is called for each BoundProcessor before calling SubscriptionConsumer::subscribe method.
     */
    public function onPreSubscribe(PreSubscribe $context): void
    {
    }

    /**
     * Executed as soon as a a message is received, before it is passed to a processor
     * The extension may set a result. If the result is set the processor is not called
     * The processor could be changed or decorated at this point.
     */
    public function onMessageReceived(MessageReceived $context): void
    {
    }

    /**
     * Executed at the very end of consumption callback. The message has already been acknowledged.
     * The message result could not be changed.
     * The consumption could be interrupted at this point.
     */
    public function onPostMessageReceived(PostMessageReceived $context): void
    {
    }

    /**
     * Executed when a message is processed by a processor or a result was set in onMessageReceived extension method.
     * BEFORE the message status was sent to the broker
     * The result could be changed at this point.
     */
    public function onResult(MessageResult $context): void
    {
    }

    /**
     * Execute if a processor throws an exception.
     * The result could be set, if result is not set the exception is thrown again.
     */
    public function onProcessorException(ProcessorException $context): void
    {
    }

    /**
     * Executed only once at the very beginning of the QueueConsumer::consume method call.
     */
    public function onStart(Start $context): void
    {
    }

    /**
     * Executed only once just before QueueConsumer::consume returns.
     */
    public function onEnd(End $context): void
    {
    }

    /**
     * Executed only once at the very beginning of the QueueConsumer::consume method call.
     * BEFORE onStart extension method.
     */
    public function onInitLogger(InitLogger $context): void
    {
    }
}
