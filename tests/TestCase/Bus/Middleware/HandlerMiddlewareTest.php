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

namespace Cake\Queue\Test\TestCase\Bus\Middleware;

use Cake\Event\EventList;
use Cake\Log\Engine\ArrayLog;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Middleware\HandlerMiddleware;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use RuntimeException;
use stdClass;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\NullReturnJob;
use TestApp\Job\RejectJob;
use TestApp\Job\RequeueJob;
use TestApp\Job\ThrowJob;
use TestApp\Job\UnknownStringJob;

class HandlerMiddlewareTest extends TestCase
{
    public function testSuccessWrapsAckAndDispatchesEvents(): void
    {
        $logger = new ArrayLog();
        $handler = new HandlerMiddleware($logger);
        $events = new EventList();
        /** @var \Cake\Event\EventManager $eventManager */
        $eventManager = $handler->getEventManager();
        $eventManager->setEventList($events);

        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage(LogToDebugJob::class)),
        ]);
        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());

        $names = [];
        foreach ($events as $event) {
            $names[] = $event->getName();
        }

        $this->assertSame(
            ['Processor.message.seen', 'Processor.message.start', 'Processor.message.success'],
            $names,
        );

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Message processed successfully', $logs[0]);
    }

    public function testRejectWrapsReject(): void
    {
        $handler = new HandlerMiddleware();
        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage(RejectJob::class)),
        ]);

        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::REJECT, $handled->getResult());
    }

    public function testExplicitRequeueWrapsRequeue(): void
    {
        $handler = new HandlerMiddleware();
        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage(RequeueJob::class)),
        ]);

        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::REQUEUE, $handled->getResult());
    }

    public function testUnknownStringResultWrapsRequeue(): void
    {
        $handler = new HandlerMiddleware();
        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage(UnknownStringJob::class)),
        ]);

        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::REQUEUE, $handled->getResult());
    }

    public function testNullResultWrapsAck(): void
    {
        $handler = new HandlerMiddleware();
        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage(NullReturnJob::class)),
        ]);

        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::ACK, $handled->getResult());
    }

    public function testInvalidCallableWrapsReject(): void
    {
        $handler = new HandlerMiddleware();
        $events = new EventList();
        /** @var \Cake\Event\EventManager $eventManager */
        $eventManager = $handler->getEventManager();
        $eventManager->setEventList($events);

        $envelope = new Envelope(new stdClass(), [
            new JobMessageStamp($this->jobMessage('NotValid\\ClassName\\FakeJob')),
        ]);
        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $this->assertSame(InteropProcessor::REJECT, $handled->getResult());

        $names = [];
        foreach ($events as $event) {
            $names[] = $event->getName();
        }

        $this->assertSame(['Processor.message.seen', 'Processor.message.invalid'], $names);
    }

    public function testExceptionWrapsRequeueResult(): void
    {
        $logger = new ArrayLog();
        $handler = new HandlerMiddleware($logger);

        $queueMessage = new NullMessage((string)json_encode([
            'class' => [ThrowJob::class, 'execute'],
            'data' => [],
        ]));
        $context = (new NullConnectionFactory())->createContext();
        $jobMessage = new Message($queueMessage, $context);

        $envelope = new Envelope(new stdClass(), [new JobMessageStamp($jobMessage)]);
        $finished = (new MessageBus([$handler]))->handle($envelope);

        $handled = $finished->getLastStamp(HandledStamp::class);
        $this->assertNotNull($handled);
        $result = $handled->getResult();
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);
        $jobException = $queueMessage->getProperty('jobException');
        $this->assertInstanceOf(RuntimeException::class, $jobException);
        $this->assertSame('Job failed.', $jobException->getMessage());

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Message encountered exception: Job failed.', $logs[0]);
    }

    public function testRequiresJobMessageStamp(): void
    {
        $handler = new HandlerMiddleware();

        $this->expectException(RuntimeException::class);

        (new MessageBus([$handler]))->handle(new Envelope(new stdClass()));
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
}
