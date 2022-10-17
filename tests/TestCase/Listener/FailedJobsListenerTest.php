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
namespace Cake\Queue\Test\TestCase\Mailer;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Queue\Job\Message;
use Cake\Queue\Listener\FailedJobsListener;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use TestApp\Job\LogToDebugJob;

class FailedJobsListenerTest extends TestCase
{
    protected $fixtures = [
        'plugin.Cake/Queue.FailedJobs',
    ];

    public function setUp(): void
    {
        parent::setUp();

        QueueManager::setConfig('example_config', [
            'url' => 'null:',
            'storeFailedJobs' => true,
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        QueueManager::drop('example_config');
    }

    public function testFailedJobIsAddedWhenEventIsFired()
    {
        $parsedBody = [
            'class' => [LogToDebugJob::class, 'execute'],
            'data' => ['example_key' => 'example_value'],
            'requeueOptions' => [
                'config' => 'example_config',
                'priority' => 'example_priority',
                'queue' => 'example_queue',
            ],
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $event = new Event(
            'Consumption.LimitAttemptsExtension.failed',
            $message,
            ['exception' => 'some message']
        );

        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');
        $failedJobsTable->deleteAll(['1=1']);

        EventManager::instance()->on(new FailedJobsListener());
        EventManager::instance()->dispatch($event);

        $this->assertSame(1, $failedJobsTable->find()->count());

        $failedJob = $failedJobsTable->find()->first();

        $this->assertSame(LogToDebugJob::class, $failedJob->class);
        $this->assertSame('execute', $failedJob->method);
        $this->assertSame(json_encode(['example_key' => 'example_value']), $failedJob->data);
        $this->assertSame('example_config', $failedJob->config);
        $this->assertSame('example_priority', $failedJob->priority);
        $this->assertSame('example_queue', $failedJob->queue);
        $this->assertStringContainsString('some message', $failedJob->exception);
    }
}
