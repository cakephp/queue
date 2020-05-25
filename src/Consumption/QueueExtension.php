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
namespace Cake\Queue\Consumption;

use Cake\Event\EventDispatcherTrait;
use Cake\Log\LogTrait;
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
use Enqueue\Consumption\Result;
use Psr\Log\LoggerInterface;

class QueueExtension implements ExtensionInterface
{
    use EventDispatcherTrait;
    use LogTrait;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $maxIterations;

    /**
     * @var int
     */
    protected $maxRuntime;

    /**
     * @var int
     */
    protected $iterations = 0;

    /**
     * @var float
     */
    protected $runtime = 0;

    /**
     * @var float
     */
    protected $startedAt;

    /**
     * @param int $maxIterations Max. iterations.
     * @param int $maxRuntime Max. runtime.
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     */
    public function __construct(int $maxIterations, int $maxRuntime, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->maxIterations = $maxIterations;
        $this->maxRuntime = $maxRuntime;
        $this->startedAt = microtime(true);
        $this->logger->debug(sprintf('Max Iterations: %s', $this->maxIterations));
        $this->logger->debug(sprintf('Max Runtime: %s', $this->maxRuntime));
    }

    /**
     * Executed at the very end of consumption callback. The message has already been acknowledged.
     * The message result could not be changed.
     * The consumption could be interrupted at this point.
     *
     * @param \Enqueue\Consumption\Context\PostMessageReceived $context Context.
     * @return void
     */
    public function onPostMessageReceived(PostMessageReceived $context): void
    {
        $result = $context->getResult();
        if ($result instanceof Result && $result->getReason()) {
            return;
        }

        $this->runtime = microtime(true) - $this->startedAt;
        $this->logger->debug(sprintf('Runtime: %s', $this->runtime));

        if ($this->maxRuntime > 0 && $this->runtime >= $this->maxRuntime) {
            $this->logger->debug('Max runtime reached, exiting');
            $this->dispatchEvent('Processor.maxRuntime');
            $context->interruptExecution(0);
        }

        $this->iterations++;
        $this->logger->debug(sprintf('Iterations: %s', $this->iterations));
        if ($this->maxIterations > 0 && $this->iterations >= $this->maxIterations) {
            $this->logger->debug('Max iterations reached, exiting');
            $this->dispatchEvent('Processor.maxIterations');
            $context->interruptExecution(0);
        }
    }

    /**
     * Executed at every new cycle before calling SubscriptionConsumer::consume method.
     * The consumption could be interrupted at this step.
     *
     * @param \Enqueue\Consumption\Context\PreConsume $context Context.
     * @return void
     */
    public function onPreConsume(PreConsume $context): void
    {
        $this->runtime = microtime(true) - $this->startedAt;
        $this->logger->debug(sprintf('Runtime: %s', $this->runtime));

        if ($this->maxRuntime > 0 && $this->runtime >= $this->maxRuntime) {
            $this->logger->debug('Max runtime reached, exiting');
            $this->dispatchEvent('Processor.maxRuntime');
            $context->interruptExecution(0);
        }
    }

    /**
     * The method is called after SubscriptionConsumer::consume method exits.
     * The consumption could be interrupted at this point.
     *
     * @param \Enqueue\Consumption\Context\PostConsume $context Context.
     * @return void
     */
    public function onPostConsume(PostConsume $context): void
    {
    }

    /**
     * The method is called for each BoundProcessor before calling SubscriptionConsumer::subscribe method.
     *
     * @param \Enqueue\Consumption\Context\PreSubscribe $context Context.
     * @return void
     */
    public function onPreSubscribe(PreSubscribe $context): void
    {
    }

    /**
     * Executed as soon as a a message is received, before it is passed to a processor
     * The extension may set a result. If the result is set the processor is not called
     * The processor could be changed or decorated at this point.
     *
     * @param \Enqueue\Consumption\Context\MessageReceived $context Context.
     * @return void
     */
    public function onMessageReceived(MessageReceived $context): void
    {
    }

    /**
     * Executed when a message is processed by a processor or a result was set in onMessageReceived extension method.
     * BEFORE the message status was sent to the broker
     * The result could be changed at this point.
     *
     * @param \Enqueue\Consumption\Context\MessageResult $context Context.
     * @return void
     */
    public function onResult(MessageResult $context): void
    {
    }

    /**
     * Execute if a processor throws an exception.
     * The result could be set, if result is not set the exception is thrown again.
     *
     * @param \Enqueue\Consumption\Context\ProcessorException $context Context.
     * @return void
     */
    public function onProcessorException(ProcessorException $context): void
    {
    }

    /**
     * Executed only once at the very beginning of the QueueConsumer::consume method call.
     *
     * @param \Enqueue\Consumption\Context\Start $context Context.
     * @return void
     */
    public function onStart(Start $context): void
    {
    }

    /**
     * Executed only once just before QueueConsumer::consume returns.
     *
     * @param \Enqueue\Consumption\Context\End $context Context.
     * @return void
     */
    public function onEnd(End $context): void
    {
    }

    /**
     * Executed only once at the very beginning of the QueueConsumer::consume method call.
     * BEFORE onStart extension method.
     *
     * @param \Enqueue\Consumption\Context\InitLogger $context Context.
     * @return void
     */
    public function onInitLogger(InitLogger $context): void
    {
    }
}
