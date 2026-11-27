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
namespace Cake\Queue\Bus\Middleware;

use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusProcessor;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Job\JobExecutionTrait;
use Cake\Queue\Job\Message;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Terminal consume middleware: resolves the job callable and invokes it.
 *
 * Runs the shared `JobExecutionTrait` flow (same events, payloads and result
 * mapping as any job-running processor) and wraps the outcome in a
 * `HandledStamp` instead of returning it directly. Requires a
 * `JobMessageStamp` on the envelope.
 */
final class HandlerMiddleware implements BusMiddlewareInterface
{
    use EventDispatcherTrait;
    use JobExecutionTrait;

    /**
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     */
    public function __construct(
        protected readonly ?LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $jobMessage = $envelope->getLastStamp(JobMessageStamp::class)?->getMessage();
        if ($jobMessage === null) {
            throw new RuntimeException('HandlerMiddleware requires a JobMessageStamp.');
        }

        return $this->handled($envelope, $jobMessage, $this->runJob($jobMessage));
    }

    /**
     * Wrap the handler outcome, marking the message as terminally handled.
     *
     * The mark tells `BusProcessor` that a later bus throwable must bubble
     * instead of degrading a produced outcome into a requeue.
     *
     * @param \Cake\Queue\Bus\Envelope $envelope Envelope to stamp.
     * @param \Cake\Queue\Job\Message $jobMessage Consumed job message.
     * @param object|string $result Handler result.
     * @return \Cake\Queue\Bus\Envelope
     */
    protected function handled(Envelope $envelope, Message $jobMessage, string|object $result): Envelope
    {
        $jobMessage->getOriginalMessage()->setProperty(BusProcessor::NO_REQUEUE_PROPERTY, true);

        return $envelope->withStamp(new HandledStamp($result));
    }
}
