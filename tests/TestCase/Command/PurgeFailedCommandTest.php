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

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use TestApp\Job\LogToDebugJob;

/**
 * Class PurgeFailedCommandTest
 *
 * @package Queue\Test\TestCase\Command
 */
class PurgeFailedCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected $fixtures = [
        'plugin.Cake/Queue.FailedJobs',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    public function testFailedJobsAreDeleted()
    {
        $this->exec('queue purge_failed', ['y']);

        $this->assertOutputContains('Deleting 3 jobs.');
        $this->assertOutputContains('3 jobs deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->all();

        $this->assertCount(0, $results);
    }

    public function testFailedJobsAreNotDeletedIfNotConfirmed()
    {
        $this->exec('queue purge_failed', ['n']);

        $this->assertOutputNotContains('Deleting');
        $this->assertOutputNotContains('deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->all();

        $this->assertCount(3, $results);
    }

    public function testFailedJobsAreDeletedById()
    {
        $this->exec('queue purge_failed 1,2 -f');

        $this->assertOutputContains('Deleting 2 jobs.');
        $this->assertOutputContains('2 jobs deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->toArray();

        $this->assertCount(1, $results);
        $this->assertSame(3, $results[0]->id);
    }

    public function testFailedJobsAreDeletedByClass()
    {
        $class = LogToDebugJob::class;
        $this->exec("queue purge_failed --class {$class} -f");

        $this->assertOutputContains('Deleting 2 jobs.');
        $this->assertOutputContains('2 jobs deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->toArray();

        $this->assertCount(1, $results);
        $this->assertSame(2, $results[0]->id);
    }

    public function testFailedJobsAreDeletedByQueue()
    {
        $this->exec('queue purge_failed --queue alternate_queue -f');

        $this->assertOutputContains('Deleting 1 jobs.');
        $this->assertOutputContains('1 jobs deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->toArray();

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testFailedJobsAreDeletedByConfig()
    {
        $this->exec('queue purge_failed --config alternate_config -f');

        $this->assertOutputContains('Deleting 1 jobs.');
        $this->assertOutputContains('1 jobs deleted.');

        $results = $this->getTableLocator()->get('Cake/Queue.FailedJobs')->find()->toArray();

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }
}
