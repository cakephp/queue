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

namespace Cake\Queue\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\Exception\StopException;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Cake\Queue\Bus\BusProcessor;
use Cake\Queue\Command\WorkerCommand;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Interop\Queue\Processor as InteropProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use TestApp\Bus\RecordingListener;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\LogToDebugWithServiceJob;
use TestApp\Job\RequeueJob;
use TestApp\NeedsArgListener;
use TestApp\Queue\TestCustomProcessor;
use TestApp\Queue\UnresolvableProcessor;
use TestApp\WelcomeMailerListener;

/**
 * Class WorkerCommandTest
 *
 * @package Queue\Test\TestCase\Command
 */
class WorkerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use QueueTestTrait;

    protected function tearDown(): void
    {
        Configure::delete('Queue');

        parent::tearDown();
    }

    /**
     * Test that command description prints out
     */
    public function testDescriptionOutput()
    {
        $this->exec('queue worker --help');
        $this->assertOutputContains('Runs a queue worker');
    }

    /**
     * Test that queue will run for one second
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesStart()
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'null:',
            ],
        ]);
        $this->exec('queue worker --max-runtime=0');
        $this->assertEmpty($this->output());
    }

    /**
     * Test that negative limits abort with a friendly error instead of a trace.
     */
    #[RunInSeparateProcess]
    public function testQueueAbortsWithNegativeLimits(): void
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'null:',
            ],
        ]);

        $this->exec('queue worker --max-jobs=-5');
        $this->assertErrorContains('maxJobs');
    }

    /**
     * Test that queue will run for one second with valid listener
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesWithListener()
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'null:',
                'listener' => WelcomeMailerListener::class,
            ],
        ]);
        $this->exec('queue worker --max-runtime=0');
        $this->assertEmpty($this->output());
    }

    /**
     * Test that queue will abort when the passed config is not present in the app configuration.
     */
    #[RunInSeparateProcess]
    public function testQueueWillAbortWithMissingConfig()
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'null:',
                'listener' => 'InvalidListener',
            ],
        ]);

        $this->exec('queue worker --config=invalid_config --max-runtime=0');
        $this->assertErrorContains('Configuration key "invalid_config" was not found');
    }

    /**
     * Test that queue will abort with invalid listener
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesWithInvalidListener()
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'null:',
                'listener' => 'InvalidListener',
            ],
        ]);

        $this->exec('queue worker --max-runtime=0');
        $this->assertErrorContains('Listener class InvalidListener not found');
    }

    /**
     * Test that queue will write to specified logger option
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesWithLogger()
    {
        Configure::write('Queue', [
            'default' => [
                'queue' => 'default',
                'url' => 'file:///' . TMP . DS . 'queue',
            ],
        ]);
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $this->exec('queue worker --max-runtime=0 --logger=debug --verbose');
        $this->assertDebugLogContains('Consumption has started');
    }

    /**
     * Data provider for testQueueProcessesJob method
     *
     * @return array<string, array<class-string<\TestApp\Job\LogToDebugJob>|string[]>>
     */
    public static function dataProviderCallableTypes(): array
    {
        return [
            'Job Class' => [LogToDebugJob::class],
            'Array' => [[LogToDebugJob::class, 'execute']],
        ];
    }

    /**
     * Start up the worker queue, push a job, and see that it processes
     */
    #[DataProvider('dataProviderCallableTypes')]
    #[RunInSeparateProcess]
    public function testQueueProcessesJob($callable)
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push($callable);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
    }

    /**
     * Set the processor name, Start up the worker queue, push a job, and see that it processes
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesJobWithProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(LogToDebugJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --processor=processor-name --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
    }

    /**
     * Test non-default queue name
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesJobWithOtherQueue()
    {
        $config = [
            'queue' => 'other',
            'url' => 'file:///' . TMP . DS . 'other-queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['other' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('other', $config);
        QueueManager::push(LogToDebugJob::class, [], ['config' => 'other']);
        QueueManager::drop('other');

        $this->exec('queue worker --config=other --max-jobs=1 --processor=processor-name --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
    }

    /**
     * Test max-attempts option
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesJobWithMaxAttempts()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(RequeueJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-attempts=3 --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContainsExactly('RequeueJob is requeueing', 3);
    }

    /**
     * Test DI service injection works in tasks
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesJobWithDIService()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(LogToDebugWithServiceJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --processor=processor-name --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run with service infotext');
    }

    /**
     * Test that queue will process when a unique cache is configured.
     */
    #[RunInSeparateProcess]
    public function testQueueProcessesWithUniqueCacheConfigured()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
            'uniqueCache' => [
                'engine' => 'File',
            ],
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $this->exec('queue worker --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContains('Consumption has started');
    }

    /**
     * Test that queue uses default processor when no processor is specified.
     */
    #[RunInSeparateProcess]
    public function testQueueUsesDefaultProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(LogToDebugJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
    }

    /**
     * Test that queue uses custom processor when specified in configuration.
     */
    #[RunInSeparateProcess]
    public function testQueueUsesCustomProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
            'processor' => TestCustomProcessor::class,
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(LogToDebugJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
        $this->assertDebugLogContains('TestCustomProcessor processing message');
        $this->assertDebugLogContains('Message processed successfully');
    }

    /**
     * Test that queue aborts when custom processor class does not exist.
     */
    #[RunInSeparateProcess]
    public function testQueueAbortsWithNonExistentProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
            'processor' => 'NonExistentProcessor',
        ];
        Configure::write('Queue', ['default' => $config]);

        $this->exec('queue worker --max-runtime=0');

        $this->assertErrorContains('Processor class NonExistentProcessor not found');
    }

    /**
     * Test that queue aborts when custom processor does not implement Interop\Queue\Processor.
     */
    #[RunInSeparateProcess]
    public function testQueueAbortsWithInvalidProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
            'processor' => 'stdClass',
        ];
        Configure::write('Queue', ['default' => $config]);

        $this->exec('queue worker --max-runtime=0');

        $this->assertErrorContains('Processor class stdClass must implement Interop\Queue\Processor');
    }

    /**
     * Test that queue aborts when custom processor cannot be constructed.
     */
    #[RunInSeparateProcess]
    public function testQueueAbortsWithUnresolvableProcessor()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'processor' => UnresolvableProcessor::class,
        ];
        Configure::write('Queue', ['default' => $config]);

        $this->exec('queue worker --max-runtime=0');

        $this->assertErrorContains('could not be resolved');
    }

    /**
     * Test that custom processor works with listener configuration.
     */
    #[RunInSeparateProcess]
    public function testCustomProcessorWithListener()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
            'receiveTimeout' => 100,
            'processor' => TestCustomProcessor::class,
            'listener' => WelcomeMailerListener::class,
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('default', $config);
        QueueManager::push(LogToDebugJob::class);
        QueueManager::drop('default');

        $this->exec('queue worker --max-jobs=1 --logger=debug --verbose');

        $this->assertDebugLogContains('Debug job was run');
        $this->assertDebugLogContains('TestCustomProcessor processing message');
    }

    public function testListenerResolvedFromContainerWhenRegistered(): void
    {
        Configure::write('Queue', [
            'default' => ['listener' => RecordingListener::class],
        ]);

        $listener = new RecordingListener();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[RecordingListener::class, true]]);
        $container->method('get')->willReturnMap([[RecordingListener::class, $listener]]);

        $resolved = $this->resolveListener(new WorkerCommand($container));

        $this->assertSame($listener, $resolved);
    }

    public function testListenerWithRequiredArgsAbortsWithoutContainer(): void
    {
        Configure::write('Queue', [
            'default' => ['listener' => NeedsArgListener::class],
        ]);

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('could not be resolved'));

        $this->expectException(StopException::class);

        $this->resolveListener(new WorkerCommand(), $io);
    }

    public function testListenerFromContainerMustImplementListenerInterface(): void
    {
        Configure::write('Queue', [
            'default' => ['listener' => RecordingListener::class],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[RecordingListener::class, true]]);
        $container->method('get')->willReturnMap([[RecordingListener::class, new stdClass()]]);

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('must implement'));

        $this->expectException(StopException::class);

        $this->resolveListener(new WorkerCommand($container), $io);
    }

    public function testListenerContainerFailureAbortsCleanly(): void
    {
        Configure::write('Queue', [
            'default' => ['listener' => RecordingListener::class],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[RecordingListener::class, true]]);
        $container->method('get')->willThrowException(new RuntimeException('Wiring failure.'));

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('could not be resolved'));

        $this->expectException(StopException::class);

        $this->resolveListener(new WorkerCommand($container), $io);
    }

    public function testListenerInstanceIsUsedAsIs(): void
    {
        $listener = new RecordingListener();
        Configure::write('Queue', [
            'default' => ['listener' => $listener],
        ]);

        $resolved = $this->resolveListener(new WorkerCommand());

        $this->assertSame($listener, $resolved);
    }

    protected function resolveListener(WorkerCommand $command, ?ConsoleIo $io = null): ?EventListenerInterface
    {
        $args = new Arguments([], ['config' => 'default'], []);
        $io ??= $this->createStub(ConsoleIo::class);

        $method = new ReflectionMethod(WorkerCommand::class, 'getListener');

        return $method->invoke($command, $args, $io);
    }

    public function testListenerClassMustImplementListenerInterface(): void
    {
        Configure::write('Queue', [
            'default' => ['listener' => stdClass::class],
        ]);

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('must implement'));

        $this->expectException(StopException::class);

        $this->resolveListener(new WorkerCommand(), $io);
    }

    public function testProcessorResolvedFromContainerWhenRegistered(): void
    {
        QueueManager::setConfig('proc-container', ['url' => 'null:', 'processor' => TestCustomProcessor::class]);

        $processor = new TestCustomProcessor();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[TestCustomProcessor::class, true]]);
        $container->method('get')->willReturnMap([[TestCustomProcessor::class, $processor]]);

        $resolved = $this->resolveProcessor(new WorkerCommand($container), 'proc-container');

        $this->assertSame($processor, $resolved);
    }

    public function testProcessorFromContainerMustImplementProcessor(): void
    {
        QueueManager::setConfig('proc-container', ['url' => 'null:', 'processor' => TestCustomProcessor::class]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[TestCustomProcessor::class, true]]);
        $container->method('get')->willReturnMap([[TestCustomProcessor::class, new stdClass()]]);

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('must implement'));

        $this->expectException(StopException::class);

        $this->resolveProcessor(new WorkerCommand($container), 'proc-container', $io);
    }

    public function testProcessorContainerFailureAbortsCleanly(): void
    {
        QueueManager::setConfig('proc-container', ['url' => 'null:', 'processor' => TestCustomProcessor::class]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([[TestCustomProcessor::class, true]]);
        $container->method('get')->willThrowException(new RuntimeException('Wiring failure.'));

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('could not be resolved'));

        $this->expectException(StopException::class);

        $this->resolveProcessor(new WorkerCommand($container), 'proc-container', $io);
    }

    protected function resolveProcessor(
        WorkerCommand $command,
        string $config = 'default',
        ?ConsoleIo $io = null,
    ): InteropProcessor {
        $args = new Arguments([], ['config' => $config], []);
        $io ??= $this->createStub(ConsoleIo::class);

        $method = new ReflectionMethod(WorkerCommand::class, 'getProcessor');

        return $method->invoke($command, $args, $io, new NullLogger());
    }

    public function testDefaultProcessorIsBusProcessor(): void
    {
        Configure::write('Queue', ['default' => ['queue' => 'default', 'url' => 'null:']]);
        QueueManager::setConfig('default-bus', ['url' => 'null:']);

        $args = new Arguments([], ['config' => 'default-bus'], []);
        $io = $this->createStub(ConsoleIo::class);

        $method = new ReflectionMethod(WorkerCommand::class, 'getProcessor');

        $processor = $method->invoke(new WorkerCommand(), $args, $io, new NullLogger());

        $this->assertInstanceOf(BusProcessor::class, $processor);
    }
}
