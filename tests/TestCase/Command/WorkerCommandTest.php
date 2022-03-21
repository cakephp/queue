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

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Queue\QueueManager;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use TestApp\WelcomeMailer;
use TestApp\WelcomeMailerListener;

/**
 * Class WorkerCommandTest
 *
 * @package Queue\Test\TestCase\Command
 */
class WorkerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
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
        $this->exec('queue worker --max-runtime=1');
        $this->assertEmpty($this->getActualOutput());
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
        $this->exec('queue worker --max-runtime=1');
        $this->assertEmpty($this->getActualOutput());
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

        $this->exec('queue worker --config=invalid_config --max-runtime=1');
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

        $this->exec('queue worker --max-runtime=1');
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

        $this->exec('queue worker --max-runtime=1 --logger=debug --verbose');
        $this->assertDebugLogContains('Max Iterations: 0');
    }

    /**
     * Start up the worker queue, push a job, and see that it processes
     *
     * @runInSeparateProcess
     */
    public function testQueueProcessesJob()
    {
        $config = [
            'queue' => 'default',
            'url' => 'file:///' . TMP . DS . 'queue',
        ];
        Configure::write('Queue', ['default' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $callable = [WelcomeMailer::class, 'welcome'];
        $arguments = [];
        $options = ['config' => 'default'];

        QueueManager::setConfig('default', $config);
        QueueManager::push($callable, $arguments, $options);
        QueueManager::drop('default');

        $this->exec('queue worker --max-runtime=3 --logger=debug --verbose');

        $this->assertDebugLogContains('Welcome mail sent');
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
        ];
        Configure::write('Queue', ['default' => $config]);
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $callable = [WelcomeMailer::class, 'welcome'];
        $arguments = [];
        $options = ['config' => 'default'];

        QueueManager::setConfig('default', $config);
        QueueManager::push($callable, $arguments, $options);
        QueueManager::drop('default');

        $this->exec('queue worker --max-runtime=3 --processor=processor-name --logger=debug --verbose');

        $this->assertDebugLogContains('Welcome mail sent');
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
        ];
        Configure::write('Queue', ['other' => $config]);

        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $callable = [WelcomeMailer::class, 'welcome'];
        $arguments = [];
        $options = ['config' => 'other'];

        QueueManager::setConfig('other', $config);
        QueueManager::push($callable, $arguments, $options);
        QueueManager::drop('other');

        $this->exec('queue worker --config=other --max-runtime=3 --processor=processor-name --logger=debug --verbose');

        $this->assertDebugLogContains('Welcome mail sent');
    }

    protected function assertDebugLogContains($expected): void
    {
        $log = Log::engine('debug');
        $found = false;
        foreach ($log->read() as $line) {
            if (strpos($line, $expected) !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Did not find `{$expected}` in logs.");
    }
}
