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

namespace Cake\Queue\Test\TestCase\Queue;

use Cake\Event\EventList;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\Queue\Job\Message;
use Cake\Queue\Queue\Processor;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dto\OrderDto;
use TestApp\Job\DtoJob;
use TestApp\TestProcessor;
use TestApp\WelcomeMailer;

class ProcessorTest extends TestCase
{
    use QueueTestTrait;

    /**
     * Convert EventList to array.
     *
     * @param \Cake\Event\EventList $events The event list to convert.
     * @return array<\Cake\Event\EventInterface>
     */
    protected function eventListToArray(EventList $events): array
    {
        /** @var array<\Cake\Event\EventInterface> */
        return iterator_to_array($events);
    }

    /**
     * Data provider for testProcess method
     *
     * @return array<string, string[]>
     */
    public static function dataProviderTestProcess(): array
    {
        return [
            'ack' => ['processReturnAck', InteropProcessor::ACK, 'Message processed successfully', 'Processor.message.success'],
            'null' => ['processReturnNull', InteropProcessor::ACK, 'Message processed successfully', 'Processor.message.success'],
            'reject' => ['processReturnReject', InteropProcessor::REJECT, 'Message processed with rejection', 'Processor.message.reject'],
            'requeue' => ['processReturnRequeue', InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
            'string' => ['processReturnString', InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
        ];
    }

    /**
     * Test process method
     *
     * @param string $jobMethod The method name to run
     * @param string $expected The expected process result.
     * @param string $logMessage The log message based on process result.
     * @param string $dispatchedEvent The dispatched event based on process result.
     * @return void
     */
    #[DataProvider('dataProviderTestProcess')]
    public function testProcess(string $jobMethod, string $expected, string $logMessage, string $dispatchedEvent): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, $jobMethod],
            'args' => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));
        $message = new Message($queueMessage, $context);

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        /** @var \Cake\Event\EventManager $eventManager */
        $eventManager = $processor->getEventManager();
        $eventManager->setEventList($events);

        $actual = $processor->process($queueMessage, $context);
        $this->assertSame($expected, $actual);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString($logMessage, $logs[0]);

        $this->assertSame(3, $events->count());
        $eventsList = $this->eventListToArray($events);
        $this->assertSame('Processor.message.seen', $eventsList[0]->getName());
        $this->assertEquals(['queueMessage' => $queueMessage], $eventsList[0]->getData());

        // Events should contain a message with the same payload.
        $this->assertSame('Processor.message.start', $eventsList[1]->getName());
        $data = $eventsList[1]->getData();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame($message->jsonSerialize(), $data['message']->jsonSerialize());

        $this->assertSame($dispatchedEvent, $eventsList[2]->getName());
        $data = $eventsList[2]->getData();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame($message->jsonSerialize(), $data['message']->jsonSerialize());

        // Verify timing information is present in completion events
        $this->assertArrayHasKey('duration', $data);
        $this->assertIsInt($data['duration']);
        $this->assertGreaterThanOrEqual(0, $data['duration']);
    }

    /**
     * Test process when message does not have a valid callable
     *
     * @return void
     */
    public function testProcessNotValidCallable()
    {
        $messageBody = [
            'class' => ['NotValid\\ClassName\\FakeJob', 'execute'],
            'data' => ['sample_data' => 'a value'],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        /** @var \Cake\Event\EventManager $eventManager */
        $eventManager = $processor->getEventManager();
        $eventManager->setEventList($events);

        $result = $processor->process($queueMessage, $context);
        $this->assertSame(InteropProcessor::REJECT, $result);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString('Invalid callable for message. Rejecting message from queue', $logs[0]);

        $this->assertSame(2, $events->count());
        $eventsList = $this->eventListToArray($events);
        $this->assertSame('Processor.message.seen', $eventsList[0]->getName());
        $this->assertSame('Processor.message.invalid', $eventsList[1]->getName());
    }

    /**
     * When processMessage() throws an exception, test that
     * requeue will return.
     *
     * @return void
     */
    public function testProcessWillRequeueOnException()
    {
        $method = 'processAndThrowException';
        $messageBody = [
            'class' => [TestProcessor::class, $method],
            'data' => ['sample_data' => 'a value', 'key' => md5($method)],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        /** @var \Cake\Event\EventManager $eventManager */
        $eventManager = $processor->getEventManager();
        $eventManager->setEventList($events);

        $result = $processor->process($queueMessage, $context);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);

        // Verify timing information is present in exception event
        $this->assertSame(3, $events->count());
        $eventsList = $this->eventListToArray($events);
        $this->assertSame('Processor.message.exception', $eventsList[2]->getName());
        $data = $eventsList[2]->getData();
        $this->assertArrayHasKey('duration', $data);
        $this->assertIsInt($data['duration']);
        $this->assertGreaterThanOrEqual(0, $data['duration']);
    }

    /**
     * Test processJobMessage method.
     *
     * @return void
     */
    public function testProcessJobObject()
    {
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        $messageBody = [
            'class' => [WelcomeMailer::class, 'welcome'],
            'args' => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));
        $processor = new Processor();

        $result = $processor->process($queueMessage, $context);
        /** @var \Cake\Log\Engine\ArrayLog $debugLog */
        $debugLog = Log::engine('debug');
        $logs = $debugLog->read();
        Log::drop('debug');

        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Welcome mail sent', $logs[0]);

        $this->assertSame(InteropProcessor::ACK, $result);
    }

    /**
     * Test that a job receives its data hydrated back into a DTO.
     *
     * @return void
     */
    public function testProcessMessageWithDto()
    {
        $messageBody = [
            'class' => [DtoJob::class, 'execute'],
            'data' => [
                'id' => 7,
                'customer' => 'Acme Corp',
                'items' => [],
            ],
            'dtoClass' => OrderDto::class,
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));
        $processor = new Processor();

        $result = $processor->process($queueMessage, $context);

        $this->assertSame(InteropProcessor::ACK, $result);
        $this->assertInstanceOf(OrderDto::class, DtoJob::$lastDto);
        $this->assertSame(7, DtoJob::$lastDto->id);
        $this->assertSame('Acme Corp', DtoJob::$lastDto->customer);

        DtoJob::$lastDto = null;
    }

    /**
     * Test processMessage method.
     *
     * @return void
     */
    public function testProcessMessage()
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage((string)json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        $processor = new Processor();

        $result = $processor->processMessage($message);
        $this->assertSame(InteropProcessor::ACK, $result);
        $this->assertInstanceOf(Message::class, TestProcessor::$lastProcessMessage);
    }
}
