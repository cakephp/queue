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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Job;

use Cake\Event\EventDispatcherTrait;
use Enqueue\Consumption\Result;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Shared job-execution flow: resolve the job callable and invoke it.
 *
 * Used by `HandlerMiddleware` (wrapping the outcome in a `HandledStamp`)
 * and by job-running processors (returning it directly), so event names,
 * payloads and result mapping stay identical on every path.
 *
 * Requires the using class to expose the event dispatcher (pulled in
 * automatically) and a `protected LoggerInterface|null $logger` property
 * for the debug trail. The accessor below defaults an unset logger to a
 * `NullLogger`, so legacy hierarchies skipping the parent constructor
 * keep working as before.
 *
 * @property \Psr\Log\LoggerInterface|null $logger Logger instance.
 */
trait JobExecutionTrait
{
    use EventDispatcherTrait;

    /**
     * Logger for the debug trail, defaulting to a `NullLogger`.
     */
    protected function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /**
     * Run the job and map its outcome to a transport status.
     *
     * Emits the `Processor.message.*` events on the host's own event
     * manager with the consume-pipeline payloads: `seen`
     * (`queueMessage`), `start` (`message`), `success` / `reject` /
     * `failure` (`message`, `duration`), `exception` (`message`,
     * `exception`, `duration`) and `invalid` (`message`). A `null` job
     * return becomes `ACK`; anything but `ACK` / `REJECT` becomes `REQUEUE`.
     *
     * @param \Cake\Queue\Job\Message $jobMessage Consumed job message.
     * @return object|string with __toString method implemented
     */
    public function runJob(Message $jobMessage): string|object
    {
        $this->dispatchJobEvent('Processor.message.seen', [
            'queueMessage' => $jobMessage->getOriginalMessage(),
        ]);

        try {
            $jobMessage->getCallable();
        } catch (Throwable $throwable) {
            $this->logger()->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchJobEvent('Processor.message.invalid', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $startTime = microtime(true) * 1000;
        $this->dispatchJobEvent('Processor.message.start', ['message' => $jobMessage]);

        try {
            $response = $this->processMessage($jobMessage);
        } catch (Throwable $throwable) {
            $jobMessage->getOriginalMessage()->setProperty('jobException', $throwable);

            $this->logger()->debug(sprintf('Message encountered exception: %s', $throwable->getMessage()));
            $this->dispatchJobEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $throwable,
                'duration' => (int)((microtime(true) * 1000) - $startTime),
            ]);

            return Result::requeue('Exception occurred while processing message');
        }

        $duration = (int)((microtime(true) * 1000) - $startTime);

        if ($response === InteropProcessor::ACK) {
            $this->logger()->debug('Message processed successfully');
            $this->dispatchJobEvent('Processor.message.success', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger()->debug('Message processed with rejection');
            $this->dispatchJobEvent('Processor.message.reject', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::REJECT;
        }

        $this->logger()->debug('Message processed with failure, requeuing');
        $this->dispatchJobEvent('Processor.message.failure', [
            'message' => $jobMessage,
            'duration' => $duration,
        ]);

        return InteropProcessor::REQUEUE;
    }

    /**
     * Dispatch a lifecycle event.
     *
     * Listener failures are not isolated: a throwing listener bubbles out
     * of the job run (and, on consume, into a transport requeue), exactly
     * as with the pre-bus processor.
     *
     * @param string $name Event name.
     * @param array<string, mixed> $data Event payload.
     */
    protected function dispatchJobEvent(string $name, array $data): void
    {
        $this->dispatchEvent($name, $data);
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
            return InteropProcessor::ACK;
        }

        if (!is_string($response) && !is_object($response)) {
            return InteropProcessor::REJECT;
        }

        return $response;
    }
}
