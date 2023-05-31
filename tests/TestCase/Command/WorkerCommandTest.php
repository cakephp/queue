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
use Cake\Queue\QueueManager;
use Cake\Queue\Test\test_app\src\Job\LogToDebugWithServiceJob;
use Cake\Queue\Test\TestCase\DebugLogTrait;
use Cake\TestSuite\TestCase;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\RequeueJob;
use TestApp\WelcomeMailerListener;

/**
 * Class WorkerCommandTest
 *
 * @package Queue\Test\TestCase\Command
 */
class WorkerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use DebugLogTrait;

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
     *
     * @runInSeparateProcess
     */
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
     * Test that queue will run for one second with valid listener
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     * @return array
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
     *
     * @dataProvider dataProviderCallableTypes
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
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
     *
     * @runInSeparateProcess
     */
    public function testQueueProcessesJobWithDIService()
    {
        $this->skipIf(version_compare(Configure::version(), '4.2', '<'), 'DI Container is only available since CakePHP 4.2');
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
}
