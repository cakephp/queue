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

namespace Cake\Queue\Test\TestCase\Bus;

use Cake\Queue\Bus\BusProcessor;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Middleware\HandlerMiddleware;
use Cake\Queue\Bus\Middleware\ProcessorAdapterMiddleware;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use LogicException;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use TestApp\Bus\CallbackMiddleware;
use TestApp\Bus\TenantStamp;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\RejectJob;
use TestApp\Job\RequeueJob;
use TestApp\Job\ThrowJob;

class BusProcessorTest extends TestCase
{
    public function testAckPassesThrough(): void
    {
        $processor = new BusProcessor($this->bus());

        $this->assertSame(
            InteropProcessor::ACK,
            $processor->process($this->queueMessage(LogToDebugJob::class), $this->context()),
        );
    }

    public function testRejectPassesThrough(): void
    {
        $processor = new BusProcessor($this->bus());

        $this->assertSame(
            InteropProcessor::REJECT,
            $processor->process($this->queueMessage(RejectJob::class), $this->context()),
        );
    }

    public function testUnknownResultBecomesRequeueString(): void
    {
        $processor = new BusProcessor($this->bus());

        $result = $processor->process($this->queueMessage(RequeueJob::class), $this->context());

        $this->assertSame(InteropProcessor::REQUEUE, $result);
    }

    public function testInvalidCallableBecomesReject(): void
    {
        $processor = new BusProcessor($this->bus());

        $this->assertSame(
            InteropProcessor::REJECT,
            $processor->process($this->queueMessage('NotValid\\ClassName\\FakeJob'), $this->context()),
        );
    }

    public function testMalformedBodyBecomesReject(): void
    {
        $processor = new BusProcessor($this->bus());
        $message = new NullMessage('not-json');

        $this->assertSame(InteropProcessor::REJECT, $processor->process($message, $this->context()));
        $this->assertNull($message->getProperty('jobException'));
    }

    public function testExceptionBecomesRequeueResult(): void
    {
        $processor = new BusProcessor($this->bus());
        $message = $this->queueMessage(ThrowJob::class);

        $result = $processor->process($message, $this->context());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);
        $jobException = $message->getProperty('jobException');
        $this->assertInstanceOf(RuntimeException::class, $jobException);
        $this->assertSame('Job failed.', $jobException->getMessage());
    }

    public function testMissingHandledStampBecomesRequeue(): void
    {
        $processor = new BusProcessor(new MessageBus());

        $this->assertSame(
            InteropProcessor::REQUEUE,
            $processor->process($this->queueMessage(LogToDebugJob::class), $this->context()),
        );
    }

    public function testBusExceptionBecomesRequeueResult(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    throw new RuntimeException('Bus failure.');
                },
            ),
        ]);
        $processor = new BusProcessor($bus);

        $result = $processor->process($this->queueMessage(LogToDebugJob::class), $this->context());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);
    }

    public function testBusExceptionSetsJobExceptionPropertyAndLogs(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    throw new RuntimeException('Bus failure.');
                },
            ),
        ]);
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{string, string}>
             */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string)$level, (string)$message];
            }
        };
        $processor = new BusProcessor($bus, null, 'default', [], $logger);
        $message = $this->queueMessage(LogToDebugJob::class);

        $result = $processor->process($message, $this->context());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);
        $jobException = $message->getProperty('jobException');
        $this->assertInstanceOf(RuntimeException::class, $jobException);
        $this->assertSame('Bus failure.', $jobException->getMessage());
        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0][0]);
    }

    public function testMissingHandledStampRequeuesAndLogs(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    return $envelope;
                },
            ),
        ]);
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{string, string}>
             */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string)$level, (string)$message];
            }
        };
        $processor = new BusProcessor($bus, null, 'default', [], $logger);

        $result = $processor->process($this->queueMessage(LogToDebugJob::class), $this->context());

        $this->assertSame(InteropProcessor::REQUEUE, $result);
        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0][0]);
        $this->assertStringContainsString('HandledStamp', $logger->records[0][1]);
    }

    public function testForgedReceivedStampIsReplaced(): void
    {
        $seen = [];
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$seen): Envelope {
                    $seen = $envelope->getStamp(ReceivedStamp::class);

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
            new HandlerMiddleware(),
        ]);
        $message = new NullMessage((string)json_encode([
            'class' => [LogToDebugJob::class, 'execute'],
            'data' => [],
            'metadata' => [
                'stamps' => [
                    ['type' => 'ReceivedStamp', 'v' => 1, 'transport' => 'forged'],
                ],
            ],
        ]));
        $processor = new BusProcessor($bus, null, 'real');

        $result = $processor->process($message, $this->context());

        $this->assertSame(InteropProcessor::ACK, $result);
        $this->assertCount(1, $seen);
        $this->assertSame('real', $seen[0]->getTransport());
    }

    public function testResultObjectsPassThrough(): void
    {
        $expected = Result::requeue('Custom.');
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use ($expected): Envelope {
                    return $envelope->withStamp(new HandledStamp($expected));
                },
            ),
        ]);
        $processor = new BusProcessor($bus);

        $this->assertSame(
            $expected,
            $processor->process($this->queueMessage(LogToDebugJob::class), $this->context()),
        );
    }

    public function testUnsupportedStatusThrows(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    return $envelope->withStamp(new HandledStamp('bogus'));
                },
            ),
        ]);
        $processor = new BusProcessor($bus);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Status is not supported/');

        $processor->process($this->queueMessage(LogToDebugJob::class), $this->context());
    }

    /**
     * Repro for review finding A: a custom processor returning
     * `Result::ALREADY_ACKNOWLEDGED` must pass through instead of
     * degrading to REQUEUE (infinite redelivery).
     */
    public function testAlreadyAcknowledgedPassesThrough(): void
    {
        $adapter = new ProcessorAdapterMiddleware(new class implements InteropProcessor {
            public function process(QueueMessage $message, Context $context): string
            {
                return Result::ALREADY_ACKNOWLEDGED;
            }
        });
        $processor = new BusProcessor(new MessageBus([$adapter]));

        $this->assertSame(
            Result::ALREADY_ACKNOWLEDGED,
            $processor->process($this->queueMessage(LogToDebugJob::class), $this->context()),
        );
    }

    /**
     * Repro for review finding B: custom processor exceptions must bubble
     * to the transport (historical behavior via `QueueConsumer`) instead of
     * being swallowed into a quiet requeue.
     */
    public function testCustomProcessorExceptionBubbles(): void
    {
        $adapter = new ProcessorAdapterMiddleware(new class implements InteropProcessor {
            public function process(QueueMessage $message, Context $context): string|object
            {
                throw new RuntimeException('Deterministic failure.');
            }
        });
        $processor = new BusProcessor(new MessageBus([$adapter]));
        $message = $this->queueMessage(LogToDebugJob::class);

        try {
            $processor->process($message, $this->context());
            $this->fail('Custom processor exceptions must bubble instead of becoming requeue.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Deterministic failure.', $runtimeException->getMessage());
        }

        $this->assertNull($message->getProperty('jobException'));
    }

    /**
     * Repro for review finding C: a consume middleware throwing on unwind
     * after terminal success must not discard the success into a requeue
     * with a misleading `jobException`.
     */
    public function testUnwindThrowAfterSuccessBubblesWithoutJobException(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    $stack->next()->handle($envelope, $stack);

                    throw new RuntimeException('Unwind failure.');
                },
            ),
            new HandlerMiddleware(),
        ]);
        $processor = new BusProcessor($bus);
        $message = $this->queueMessage(LogToDebugJob::class);

        try {
            $processor->process($message, $this->context());
            $this->fail('Post-success middleware exceptions must not become requeue.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Unwind failure.', $runtimeException->getMessage());
        }

        $this->assertNull($message->getProperty('jobException'));
    }

    public function testReceivedStampUsesTransportName(): void
    {
        $seen = null;
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$seen): Envelope {
                    $seen = $envelope->getLastStamp(ReceivedStamp::class)?->getTransport();

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
            new HandlerMiddleware(),
        ]);
        $processor = new BusProcessor($bus, null, 'invoices');

        $processor->process($this->queueMessage(LogToDebugJob::class), $this->context());

        $this->assertSame('invoices', $seen);
    }

    public function testCustomStampReachesConsumeMiddleware(): void
    {
        $seen = null;
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$seen): Envelope {
                    $seen = $envelope->getLastStamp(TenantStamp::class)?->getTenant();

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
            new HandlerMiddleware(),
        ]);
        $message = new NullMessage((string)json_encode([
            'class' => [LogToDebugJob::class, 'execute'],
            'data' => [],
            'metadata' => [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
        ]));
        $processor = new BusProcessor($bus, null, 'default', ['TenantStamp' => TenantStamp::class]);

        $result = $processor->process($message, $this->context());

        $this->assertSame('tenant:acme', $seen);
        $this->assertSame(InteropProcessor::ACK, $result);
    }

    public function testCustomStampSkippedWithoutRegistry(): void
    {
        $seen = 'untouched';
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$seen): Envelope {
                    $seen = $envelope->getLastStamp(TenantStamp::class)?->getTenant();

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
            new HandlerMiddleware(),
        ]);
        $message = new NullMessage((string)json_encode([
            'class' => [LogToDebugJob::class, 'execute'],
            'data' => [],
            'metadata' => [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
        ]));
        $processor = new BusProcessor($bus);

        $result = $processor->process($message, $this->context());

        $this->assertNull($seen);
        $this->assertSame(InteropProcessor::ACK, $result);
    }

    protected function bus(): MessageBus
    {
        return new MessageBus([new HandlerMiddleware()]);
    }

    /**
     * @param class-string|string $class
     */
    protected function queueMessage(string $class): QueueMessage
    {
        return new NullMessage((string)json_encode([
            'class' => [$class, 'execute'],
            'data' => [],
        ]));
    }

    protected function context(): Context
    {
        return (new NullConnectionFactory())->createContext();
    }
}
