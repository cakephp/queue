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

namespace Cake\Queue\Test\TestCase\Job;

use Cake\Queue\Job\JobExecutionTrait;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use TestApp\Bus\RecordingListener;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\ScalarResultJob;
use TestApp\Job\ThrowJob;

class JobExecutionTraitTest extends TestCase
{
    public function testRunReturnsAckAndEmitsLifecycleEvents(): void
    {
        $host = $this->host();
        $listener = new RecordingListener();
        $host->getEventManager()->on($listener);

        $result = $host->runJob($this->jobMessage(LogToDebugJob::class));

        $this->assertSame(Processor::ACK, $result);
        $this->assertSame(
            [
                'Processor.message.seen',
                'Processor.message.start',
                'Processor.message.success',
            ],
            $listener->events,
        );
    }

    public function testRunRejectsInvalidCallable(): void
    {
        $host = $this->host();
        $listener = new RecordingListener();
        $host->getEventManager()->on($listener);

        $result = $host->runJob($this->jobMessage('NotARealJob'));

        $this->assertSame(Processor::REJECT, $result);
        $this->assertContains('Processor.message.invalid', $listener->events);
    }

    public function testRunRequeuesOnExceptionAndSetsJobException(): void
    {
        $host = $this->host();
        $listener = new RecordingListener();
        $host->getEventManager()->on($listener);

        $message = $this->jobMessage(ThrowJob::class);

        $result = $host->runJob($message);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Processor::REQUEUE, $result);
        $this->assertContains('Processor.message.exception', $listener->events);
        $jobException = $message->getOriginalMessage()->getProperty('jobException');
        $this->assertInstanceOf(RuntimeException::class, $jobException);
        $this->assertSame('Job failed.', $jobException->getMessage());
    }

    public function testThrowingListenerBubblesOut(): void
    {
        $host = $this->host();
        $host->getEventManager()->on(
            'Processor.message.success',
            static function (): void {
                throw new RuntimeException('Listener failure.');
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listener failure.');

        $host->runJob($this->jobMessage(LogToDebugJob::class));
    }

    protected function host(): object
    {
        return new class {
            use JobExecutionTrait;

            public function __construct(
                public readonly LoggerInterface $logger = new NullLogger(),
            ) {
            }
        };
    }

    protected function jobMessage(string $class): Message
    {
        $context = (new NullConnectionFactory())->createContext();
        $body = (string)json_encode([
            'class' => [$class, 'execute'],
            'data' => [],
        ]);

        return new Message(new NullMessage($body), $context);
    }

    public function testScalarResultBecomesReject(): void
    {
        $host = $this->host();
        $context = (new NullConnectionFactory())->createContext();
        $body = (string)json_encode([
            'class' => [ScalarResultJob::class, 'run'],
            'data' => [],
        ]);
        $message = new Message(new NullMessage($body), $context);

        $result = $host->runJob($message);

        $this->assertSame(Processor::REJECT, $result);
        $this->assertNull($message->getOriginalMessage()->getProperty('jobException'));
    }
}
