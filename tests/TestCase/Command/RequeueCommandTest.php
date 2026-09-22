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

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Queue\Job\Message;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use TestApp\Dto\OrderDto;
use TestApp\Job\DtoJob;
use TestApp\Job\LogToDebugJob;

/**
 * Class RequeueCommandTest
 *
 * @package Queue\Test\TestCase\Command
 */
class RequeueCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use QueueTestTrait;

    /**
     * @var string[]
     */
    protected array $fixtures = [
        'plugin.Cake/Queue.FailedJobs',
    ];

    public function setUp(): void
    {
        parent::setUp();

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . uniqid('queue'),
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['default' => $config]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        Log::reset();

        QueueManager::drop('default');
    }

    public function testJobsAreRequeued()
    {
        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');
        QueueManager::drop('default');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);
        $this->assertDebugLogContainsExactly('MaxAttemptsIsThreeJob is requeueing', 0);

        $this->cleanupConsoleTrait();
        $this->exec('queue requeue --queue default', ['y']);

        $this->assertOutputContains('Requeueing 2 jobs.');
        $this->assertOutputContains('2 jobs requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');

        $this->assertDebugLogContainsExactly('Debug job was run', 1);
        $this->assertDebugLogContains('MaxAttemptsIsThreeJob is requeueing');
    }

    public function testJobsAreNotRequeuedIfNotConfirmed()
    {
        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');
        QueueManager::drop('default');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);
        $this->assertDebugLogContainsExactly('MaxAttemptsIsThreeJob is requeueing', 0);

        $this->cleanupConsoleTrait();
        $this->exec('queue requeue --queue default', ['n']);

        $this->assertOutputNotContains('Requeueing');
        $this->assertOutputNotContains('requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);
        $this->assertDebugLogContainsExactly('MaxAttemptsIsThreeJob is requeueing', 0);
    }

    public function testJobsAreRequeuedById()
    {
        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');
        QueueManager::drop('default');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);
        $this->assertDebugLogContainsExactly('MaxAttemptsIsThreeJob was run', 0);

        $this->cleanupConsoleTrait();
        $this->exec('queue requeue 1,2 -f');

        $this->assertOutputContains('Requeueing 2 jobs.');
        $this->assertOutputContains('2 jobs requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');

        $this->assertDebugLogContainsExactly('Debug job was run', 1);
        $this->assertDebugLogContains('MaxAttemptsIsThreeJob is requeueing');
    }

    public function testJobsAreRequeuedByClass()
    {
        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');
        QueueManager::drop('default');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);

        $this->cleanupConsoleTrait();
        $class = LogToDebugJob::class;
        $this->exec(sprintf('queue requeue --class %s --queue default -f', $class));

        $this->assertOutputContains('Requeueing 1 jobs.');
        $this->assertOutputContains('1 jobs requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --verbose');

        $this->assertDebugLogContainsExactly('Debug job was run', 1);
    }

    public function testJobsAreRequeuedByQueue()
    {
        $config = [
            'queue' => 'alternate_queue',
            'url' => 'file:///' . TMP . DS . 'queue' . uniqid('queue'),
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['alternate_config' => $config]);

        $this->exec('queue worker --max-jobs=3 --logger=debug --config alternate_config --verbose');
        QueueManager::drop('alternate_config');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);

        $this->cleanupConsoleTrait();
        $this->exec('queue requeue --queue alternate_queue -f');

        $this->assertOutputContains('Requeueing 1 jobs.');
        $this->assertOutputContains('1 jobs requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --config alternate_config --verbose');
        QueueManager::drop('alternate_config');

        $this->assertDebugLogContains('Debug job was run');
    }

    public function testJobsAreRequeuedByConfig()
    {
        $config = [
            'queue' => 'alternate_queue',
            'url' => 'file:///' . TMP . DS . 'queue' . uniqid('queue'),
            'receiveTimeout' => 100,
        ];
        Configure::write('Queue', ['alternate_config' => $config]);

        $this->exec('queue worker --max-jobs=3 --logger=debug --config alternate_config --verbose');
        QueueManager::drop('alternate_config');

        $this->assertDebugLogContainsExactly('Debug job was run', 0);

        $this->cleanupConsoleTrait();
        $this->exec('queue requeue --config alternate_config -f');

        $this->assertOutputContains('Requeueing 1 jobs.');
        $this->assertOutputContains('1 jobs requeued.');

        $this->exec('queue worker --max-jobs=3 --logger=debug --config alternate_config --verbose');
        QueueManager::drop('alternate_config');

        $this->assertDebugLogContains('Debug job was run');
    }

    public function testRequeuedDtoJobKeepsDtoClass()
    {
        $fsQueuePath = TMP . DS . uniqid('queue');
        QueueManager::setConfig('default', [
            'url' => 'file:///' . $fsQueuePath,
            'queue' => 'default',
        ]);

        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');
        $failedJobsTable->deleteAll(['1=1']);

        $failedJob = $failedJobsTable->newEntity([
            'class' => DtoJob::class,
            'method' => 'execute',
            'data' => json_encode(['id' => 7, 'customer' => 'Acme Corp', 'items' => []]),
            'dto_class' => OrderDto::class,
            'config' => 'default',
            'priority' => null,
            'queue' => 'default',
            'exception' => 'boom',
        ]);
        $failedJobsTable->saveOrFail($failedJob);

        $this->exec('queue requeue -f');

        $this->assertOutputContains('Requeueing 1 jobs.');
        $this->assertOutputContains('1 jobs requeued.');

        $fsQueueFile = $fsQueuePath . DS . 'enqueue.app.default';
        $this->assertFileExists($fsQueueFile);

        $contents = (string)file_get_contents($fsQueueFile);
        $this->assertStringContainsString('dtoClass', $contents);
        $this->assertStringContainsString('OrderDto', $contents);
        $this->assertStringContainsString('Acme Corp', $contents);

        unlink($fsQueueFile);
    }

    public function testRequeuedDtoJobHydratesAfterRequeue()
    {
        $parsedBody = [
            'class' => [DtoJob::class, 'execute'],
            'data' => ['id' => 7, 'customer' => 'Acme Corp', 'items' => []],
            'dtoClass' => OrderDto::class,
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $message = new Message(new NullMessage((string)json_encode($parsedBody)), $context);

        $dto = $message->getDto(OrderDto::class);

        $this->assertInstanceOf(OrderDto::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertSame('Acme Corp', $dto->customer);
    }
}
