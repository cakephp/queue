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
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use TestApp\TestProcessor;

class ProcessorTest extends TestCase
{
    public static $lastProcessMessage;

    /**
     * Data provider for testProcess method
     *
     * @return array
     */
    public static function dataProviderTestProcess(): array
    {
        return [
            'ack' => ['processReturnAck', InteropProcessor::ACK, 'Message processed sucessfully', 'Processor.message.success'],
            'null' => ['processReturnNull', InteropProcessor::ACK, 'Message processed sucessfully', 'Processor.message.success'],
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
     * @param string $dispacthedEvent The dispatched event based on process result.
     * @dataProvider dataProviderTestProcess
     * @return void
     */
    public function testProcess($jobMethod, $expected, $logMessage, $dispatchedEvent)
    {
        $messageBody = [
            'class' => [TestProcessor::class, $jobMethod],
            'args' => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        $processor->getEventManager()->setEventList($events);

        $actual = $processor->process($queueMessage, $context);
        $this->assertSame($expected, $actual);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString($logMessage, $logs[0]);

        $this->assertSame(3, $events->count());
        $this->assertSame('Processor.message.seen', $events[0]->getName());
        $this->assertEquals(['queueMessage' => $queueMessage], $events[0]->getData());

        // Events should contain a message with the same payload.
        $this->assertSame('Processor.message.start', $events[1]->getName());
        $data = $events[1]->getData();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame($message->jsonSerialize(), $data['message']->jsonSerialize());

        $this->assertSame($dispatchedEvent, $events[2]->getName());
        $data = $events[2]->getData();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame($message->jsonSerialize(), $data['message']->jsonSerialize());
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
        $queueMessage = new NullMessage(json_encode($messageBody));

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        $processor->getEventManager()->setEventList($events);

        $result = $processor->process($queueMessage, $context);
        $this->assertSame(InteropProcessor::REJECT, $result);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString('Invalid callable for message. Rejecting message from queue', $logs[0]);

        $this->assertSame(2, $events->count());
        $this->assertSame('Processor.message.seen', $events[0]->getName());
        $this->assertSame('Processor.message.invalid', $events[1]->getName());
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
        $queueMessage = new NullMessage(json_encode($messageBody));

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        $processor->getEventManager()->setEventList($events);

        $result = $processor->process($queueMessage, $context);
        $this->assertEquals(InteropProcessor::REQUEUE, $result);
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
            'class' => ['TestApp\WelcomeMailer', 'welcome'],
            'args' => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $processor = new Processor();

        $result = $processor->process($queueMessage, $context);
        $logs = Log::engine('debug')->read();
        Log::drop('debug');

        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Welcome mail sent', $logs[0]);

        $this->assertSame(InteropProcessor::ACK, $result);
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
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        $processor = new Processor();

        $result = $processor->processMessage($message);
        $this->assertSame(InteropProcessor::ACK, $result);
        $this->assertNotEmpty(TestProcessor::$lastProcessMessage);
    }
}
