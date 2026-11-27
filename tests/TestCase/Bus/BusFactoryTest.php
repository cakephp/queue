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

use Cake\Core\ContainerInterface;
use Cake\Queue\Bus\BusFactory;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Job\Message;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;
use TestApp\Bus\CountingMiddleware;
use TestApp\Bus\CustomBusProcessor;
use TestApp\Bus\NoArgRecordingMiddleware;
use TestApp\Bus\PrivateConstructorMiddleware;
use TestApp\Bus\RecordingListener;
use TestApp\Bus\RecordingMiddleware;
use TestApp\Bus\TenantStamp;
use TestApp\Job\LogToDebugJob;
use TestApp\Queue\TestCustomProcessor;

class BusFactoryTest extends TestCase
{
    use QueueTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        BusFactory::reset();
    }

    protected function tearDown(): void
    {
        BusFactory::reset();
        parent::tearDown();
    }

    public function testDispatchBusIsRebuiltPerCall(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);
        QueueManager::setConfig('bus-b', ['url' => 'null:']);

        $this->assertNotSame(BusFactory::dispatchBus('bus-a'), BusFactory::dispatchBus('bus-a'));
        $this->assertNotSame(BusFactory::dispatchBus('bus-a'), BusFactory::dispatchBus('bus-b'));
    }

    public function testResetIsHarmlessNoOp(): void
    {
        $this->assertNull(BusFactory::reset('bus-a'));
        $this->assertNull(BusFactory::reset());

        QueueManager::setConfig('bus-a', ['url' => 'null:']);
        $this->assertNull(BusFactory::reset('bus-a'));
        $this->assertInstanceOf(MessageBus::class, BusFactory::dispatchBus('bus-a'));
    }

    public function testConsumeBusIgnoresNullEventManager(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $processor = new class implements InteropProcessor {
            public function process(QueueMessage $message, Context $context): string
            {
                return InteropProcessor::ACK;
            }

            public function getEventManager()
            {
                return null;
            }
        };

        $bus = BusFactory::consumeBus(
            'bus-a',
            ['processor' => $processor, 'listener' => new RecordingListener()],
        );

        $this->assertInstanceOf(MessageBus::class, $bus);
    }

    public function testResetAll(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $dispatch = BusFactory::dispatchBus('bus-a');
        $consume = BusFactory::consumeBus('bus-a');
        BusFactory::reset();

        $this->assertNotSame($dispatch, BusFactory::dispatchBus('bus-a'));
        $this->assertNotSame($consume, BusFactory::consumeBus('bus-a'));
    }

    public function testConsumeBusRejectsInvalidProcessor(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        BusFactory::consumeBus('bus-a', ['processor' => new stdClass()]);
    }

    public function testConsumeBusRejectsInvalidLogger(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        BusFactory::consumeBus('bus-a', ['logger' => 'not-a-logger']);
    }

    public function testConsumeExtensionsRejectNegativeLimits(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        try {
            BusFactory::consumeExtensions('bus-a', new NullLogger(), null, -1);
            $this->fail('Negative maxJobs must throw.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->assertStringContainsString('maxJobs', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRuntime');

        BusFactory::consumeExtensions('bus-a', new NullLogger(), null, null, -5);
    }

    public function testPrebuiltMiddlewareIsSharedWhileClassNamesResolveFresh(): void
    {
        $shared = new CountingMiddleware();
        CountingMiddleware::$constructed = 0;
        QueueManager::setConfig('bus-shared', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => [$shared, CountingMiddleware::class],
        ]);

        BusFactory::dispatchBus('bus-shared');
        BusFactory::dispatchBus('bus-shared');

        $this->assertSame(2, CountingMiddleware::$constructed);

        $envelope = new Envelope(new Dispatch(LogToDebugJob::class, 'execute', [], null, 'default', 'bus-shared'));
        BusFactory::dispatchBus('bus-shared')->handle($envelope);
        BusFactory::dispatchBus('bus-shared')->handle($envelope);

        $this->assertSame(2, $shared->calls);
    }

    public function testInvalidUserMiddlewareThrows(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => ['NonExistentMiddleware'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::dispatchBus('bus-a');
    }

    public function testNonMiddlewareInstanceThrows(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [new stdClass()],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::consumeBus('bus-a');
    }

    public function testConsumeBusRunsUserMiddlewareAroundHandler(): void
    {
        $calls = [];
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [new RecordingMiddleware('user', $calls)],
        ]);

        $bus = BusFactory::consumeBus('bus-a');
        $finished = $bus->handle($this->envelope());

        $this->assertSame(['user.enter', 'user.exit'], $calls);
        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
    }

    public function testConsumeBusResolvesMiddlewareClassNames(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [NoArgRecordingMiddleware::class],
        ]);

        $bus = BusFactory::consumeBus('bus-a');
        $finished = $bus->handle($this->envelope());

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
    }

    public function testConsumeBusSupportsFactoryClosures(): void
    {
        $calls = [];
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [
                static function () use (&$calls): RecordingMiddleware {
                    return new RecordingMiddleware('factory', $calls);
                },
            ],
        ]);

        $bus = BusFactory::consumeBus('bus-a');
        $bus->handle($this->envelope());

        $this->assertSame(['factory.enter', 'factory.exit'], $calls);
    }

    public function testConsumeBusResolvesClassNameReturnedByFactoryClosure(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [
                static fn(): string => NoArgRecordingMiddleware::class,
            ],
        ]);

        $bus = BusFactory::consumeBus('bus-a');
        $finished = $bus->handle($this->envelope());

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
    }

    public function testConsumeBusRejectsFactoryResult(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::CONSUME_MIDDLEWARE => [
                static fn(): object => new stdClass(),
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::consumeBus('bus-a');
    }

    /**
     * Repro for the factory finding: a middleware class with required
     * constructor args that is not container-registered must fail with a
     * clear `InvalidArgumentException`, not a raw engine `ArgumentCountError`.
     */
    public function testUnregisteredMiddlewareWithDependenciesThrowsInvalidArgumentException(): void
    {
        QueueManager::setContainer(null);
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => [RecordingMiddleware::class],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::dispatchBus('bus-a');
    }

    public function testNonClassMiddlewareIdResolvesFromContainer(): void
    {
        $middleware = new CountingMiddleware();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([['mailer.transport', true]]);
        $container->method('get')->willReturnMap([['mailer.transport', $middleware]]);
        QueueManager::setContainer($container);
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => ['mailer.transport'],
        ]);

        try {
            $envelope = new Envelope(new Dispatch(LogToDebugJob::class, 'execute', [], null, 'default', 'bus-a'));
            BusFactory::dispatchBus('bus-a')->handle($envelope);

            $this->assertSame(1, $middleware->calls);
        } finally {
            QueueManager::setContainer(null);
        }
    }

    public function testFailingContainerGetAbortsCleanly(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[NoArgRecordingMiddleware::class, true]]);
        $container->method('get')->willThrowException(new RuntimeException('Wiring failure.'));
        QueueManager::setContainer($container);
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => [NoArgRecordingMiddleware::class],
        ]);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('could not be resolved');

            BusFactory::dispatchBus('bus-a');
        } finally {
            QueueManager::setContainer(null);
        }
    }

    public function testMiddlewareWithPrivateConstructorThrowsInvalidArgumentException(): void
    {
        QueueManager::setContainer(null);
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            BusFactory::DISPATCH_MIDDLEWARE => [PrivateConstructorMiddleware::class],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be resolved');

        BusFactory::dispatchBus('bus-a');
    }

    /**
     * Repro for the factory finding: a `listener` option value that is not
     * an `EventListenerInterface` must fail loudly instead of being
     * silently ignored.
     */
    public function testConsumeBusRejectsInvalidListener(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::consumeBus('bus-a', ['listener' => new stdClass()]);
    }

    public function testConsumeBusWithCustomProcessor(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $processor = new CustomBusProcessor();
        $bus = BusFactory::consumeBus('bus-a', ['processor' => $processor]);
        $finished = $bus->handle($this->envelope());

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame('custom', $handled->getResult());
        $this->assertTrue($processor->processed);
    }

    public function testConsumeBusIsRebuiltPerCall(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $processor = new CustomBusProcessor();
        $custom = BusFactory::consumeBus('bus-a', ['processor' => $processor]);
        $customResult = $custom->handle($this->envelope())->getLastStamp(HandledStamp::class);
        $this->assertNotNull($customResult);
        $this->assertSame('custom', $customResult->getResult());

        $default = BusFactory::consumeBus('bus-a');
        $defaultResult = $default->handle($this->envelope())->getLastStamp(HandledStamp::class);
        $this->assertNotNull($defaultResult);
        $this->assertSame(InteropProcessor::ACK, $defaultResult->getResult());
        $this->assertNotSame($custom, $default);
    }

    public function testConsumeBusListenerOptionAppliesPerCall(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        BusFactory::consumeBus('bus-a');

        $listener = new RecordingListener();
        $bus = BusFactory::consumeBus('bus-a', ['listener' => $listener]);
        $bus->handle($this->envelope());

        $this->assertContains('Processor.message.success', $listener->events);
    }

    public function testListenerAttachedOnDefaultPath(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $listener = new RecordingListener();
        $bus = BusFactory::consumeBus('bus-a', ['listener' => $listener]);
        $bus->handle($this->envelope());

        $this->assertContains('Processor.message.success', $listener->events);
    }

    public function testListenerAttachedOnCustomProcessorPath(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $processor = new TestCustomProcessor();
        $listener = new RecordingListener();
        $bus = BusFactory::consumeBus('bus-a', ['processor' => $processor, 'listener' => $listener]);
        $finished = $bus->handle($this->envelope());

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
        $this->assertContains('Processor.message.seen', $listener->events);
        $this->assertContains('Processor.message.start', $listener->events);
        $this->assertContains('Processor.message.success', $listener->events);
    }

    public function testListenerHearsNothingWithoutProcessorEventManager(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $processor = new class implements InteropProcessor {
            public function process(
                QueueMessage $message,
                Context $context,
            ): string {
                return InteropProcessor::ACK;
            }
        };
        $listener = new RecordingListener();
        $bus = BusFactory::consumeBus('bus-a', ['processor' => $processor, 'listener' => $listener]);
        $finished = $bus->handle($this->envelope());

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
        $this->assertSame([], $listener->events);
    }

    public function testConsumeExtensions(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        $extension = BusFactory::consumeExtensions('bus-a', new NullLogger(), 3, 10, 60);

        $this->assertInstanceOf(ChainExtension::class, $extension);
    }

    public function testStampTypesMergesCoreAndCustom(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            'stampTypes' => ['TenantStamp' => TenantStamp::class],
        ]);

        $types = BusFactory::stampTypes('bus-a');

        $this->assertSame(ReceivedStamp::class, $types['ReceivedStamp']);
        $this->assertSame(HandledStamp::class, $types['HandledStamp']);
        $this->assertSame(TenantStamp::class, $types['TenantStamp']);
    }

    public function testStampTypesDefaultsToCore(): void
    {
        QueueManager::setConfig('bus-a', ['url' => 'null:']);

        $this->assertSame(Envelope::coreStampTypes(), BusFactory::stampTypes('bus-a'));
    }

    public function testStampTypesRejectsCollision(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            'stampTypes' => ['ReceivedStamp' => TenantStamp::class],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::stampTypes('bus-a');
    }

    public function testStampTypesRejectsNonStampClass(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            'stampTypes' => ['Whatever' => stdClass::class],
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::stampTypes('bus-a');
    }

    public function testStampTypesRejectsNonArray(): void
    {
        QueueManager::setConfig('bus-a', [
            'url' => 'null:',
            'stampTypes' => 'nope',
        ]);

        $this->expectException(InvalidArgumentException::class);

        BusFactory::stampTypes('bus-a');
    }

    protected function envelope(): Envelope
    {
        $context = (new NullConnectionFactory())->createContext();
        $body = (string)json_encode([
            'class' => [LogToDebugJob::class, 'execute'],
            'data' => [],
        ]);
        $message = new Message(new NullMessage($body), $context);

        return (new Envelope(new stdClass()))->withStamp(
            new ReceivedStamp('default'),
            new JobMessageStamp($message),
        );
    }
}
