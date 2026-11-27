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
namespace Cake\Queue\Bus;

use Cake\Core\ContainerInterface;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Job\Message;
use Enqueue\Consumption\Result;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Thin bridge between the Enqueue transport and the consume bus.
 *
 * Parses the transport body into an envelope (tolerant: unknown stamps and
 * corrupt rows are skipped), stamps it received, runs the consume bus and
 * maps the handler outcome back to the transport contract, mirroring the
 * `QueueConsumer` status table: `ACK` / `REJECT` /
 * `ALREADY_ACKNOWLEDGED` and `Result` objects pass through, a missing
 * outcome becomes `REQUEUE`, anything else throws. Throwables escaping the
 * bus after terminal handling (custom processor failures, post-success
 * middleware failures) bubble instead of degrading to requeue; only
 * pre-terminal bus failures are normalized to requeue.
 */
final class BusProcessor implements InteropProcessor
{
    /**
     * Message property marking the envelope as terminally handled.
     *
     * Set by terminal middleware once a `HandledStamp` exists (or by the
     * custom processor adapter before rethrowing). When present, a bus
     * throwable must bubble: converting it to requeue would either discard
     * a success or silence a deterministic failure.
     */
    public const NO_REQUEUE_PROPERTY = 'busNoRequeue';

    /**
     * @param \Cake\Queue\Bus\MessageBus $bus Consume bus to handle envelopes.
     * @param \Cake\Core\ContainerInterface|null $container DI container instance.
     * @param string $transport Transport name recorded on the `ReceivedStamp`.
     * @param array<string, class-string<\Cake\Queue\Bus\StampInterface>> $stampTypes
     *   App-defined stamp types used when parsing the body (merged over core types).
     * @param \Psr\Log\LoggerInterface|null $logger Logger for bus-level failures outside job handling.
     */
    public function __construct(
        protected readonly MessageBus $bus,
        protected readonly ?ContainerInterface $container = null,
        protected readonly string $transport = 'default',
        protected readonly array $stampTypes = [],
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $body = json_decode($message->getBody(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $envelope = Envelope::fromTransportBody($body, $this->stampTypes)
            ->withoutStamp(ReceivedStamp::class)
            ->withStamp(new ReceivedStamp($this->transport));

        try {
            $envelope = $envelope->withStamp(
                new JobMessageStamp(new Message($message, $context, $this->container)),
            );
            $finished = $this->bus->handle($envelope);
            $result = $finished->getLastStamp(HandledStamp::class)?->getResult();
        } catch (Throwable $throwable) {
            if ($message->getProperty(self::NO_REQUEUE_PROPERTY) === true) {
                ($this->logger ?? new NullLogger())->error(sprintf(
                    'Consume bus threw after terminal handling (%s): %s',
                    $throwable::class,
                    $throwable->getMessage(),
                ));

                throw $throwable;
            }

            $message->setProperty('jobException', $throwable);
            ($this->logger ?? new NullLogger())->error(sprintf(
                'Consume bus threw outside job handling (%s): %s',
                $throwable::class,
                $throwable->getMessage(),
            ));

            return Result::requeue('Exception occurred while processing message');
        }

        if ($result === InteropProcessor::ACK) {
            return InteropProcessor::ACK;
        }

        if ($result === InteropProcessor::REJECT) {
            return InteropProcessor::REJECT;
        }

        if ($result === Result::ALREADY_ACKNOWLEDGED) {
            return Result::ALREADY_ACKNOWLEDGED;
        }

        if ($result === InteropProcessor::REQUEUE) {
            return InteropProcessor::REQUEUE;
        }

        if ($result instanceof Result) {
            return $result;
        }

        if ($result === null) {
            ($this->logger ?? new NullLogger())->error(
                'Consume pipeline produced no result: no `HandledStamp` on the finished envelope. ' .
                'Requeueing the message. To drop a message intentionally, end the pipeline with ' .
                '`$envelope->withStamp(new HandledStamp(ACK))` instead of returning the envelope untouched.',
            );

            return InteropProcessor::REQUEUE;
        }

        throw new LogicException(sprintf(
            'Status is not supported: `%s`.',
            is_string($result) ? $result : get_debug_type($result),
        ));
    }
}
