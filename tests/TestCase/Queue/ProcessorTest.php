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
namespace Queue\Test\TestCase\Queue;

use Cake\Event\EventList;
use Cake\Log\Engine\ArrayLog;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use Queue\Job\Message;
use Queue\Queue\Processor;

class ProcessorTest extends TestCase
{
    public static $lastProcessMessage;

    /**
     * Data provider for testProcess method
     *
     * @return array
     */
    public function dataProviderTestProcess(): array
    {
        return [
            ['processReturnAck', InteropProcessor::ACK, 'Message processed sucessfully', 'Processor.message.success'],
            ['processReturnReject', InteropProcessor::REJECT, 'Message processed with rejection', 'Processor.message.reject'],
            ['processReturnRequeue', InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
            ['processReturnString', InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
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
            "queue" => "default",
            "class" => [static::class, $jobMethod],
            "args" => [],
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
        $this->assertEquals($expected, $actual);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString($logMessage, $logs[0]);

        $this->assertSame(3, $events->count());
        $this->assertSame('Processor.message.seen', $events[0]->getName());
        $this->assertEquals(['queueMessage' => $queueMessage], $events[0]->getData());

        $this->assertSame('Processor.message.start', $events[1]->getName());
        $this->assertEquals(['message' => $message], $events[1]->getData());

        $this->assertSame($dispatchedEvent, $events[2]->getName());
        $this->assertEquals(['message' => $message], $events[2]->getData());
    }

    /**
     * Test process when message does not have a valid callable
     *
     * @return void
     */
    public function testProcessNotValidCallable()
    {
        $messageBody = [
            "queue" => "default",
            "class" => ["NotValid\\ClassName\\FakeJob", "execute"],
            "args" => [
                [
                    "data" => ['sample_data' => 'a value'],
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));

        $events = new EventList();
        $logger = new ArrayLog();
        $processor = new Processor($logger);
        $processor->getEventManager()->setEventList($events);

        $actual = $processor->process($queueMessage, $context);
        $expected = InteropProcessor::REJECT;
        $this->assertEquals($expected, $actual);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('debug', $logs[0]);
        $this->assertStringContainsString('Invalid callable for message. Rejecting message from queue', $logs[0]);

        $this->assertSame(2, $events->count());
        $this->assertSame('Processor.message.seen', $events[0]->getName());
        $this->assertSame('Processor.message.invalid', $events[1]->getName());
    }

    /**
     * Test processMessage method.
     *
     * @return void
     */
    public function testProcessMessage()
    {
        $messageBody = [
            "queue" => "default",
            "class" => [static::class, "processReturnAck"],
            "args" => [],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        $processor = new Processor();

        $actual = $processor->processMessage($message);
        $expected = InteropProcessor::ACK;
        $this->assertSame($expected, $actual);
        $this->assertNotEmpty(static::$lastProcessMessage);
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnNull(Message $message)
    {
        static::$lastProcessMessage = $message;

        return null;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnReject(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::REJECT;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnAck(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::ACK;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnRequeue(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::REQUEUE;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     * @return null
     */
    public static function processReturnString(Message $message)
    {
        static::$lastProcessMessage = $message;

        return 'invalid value';
    }

    /**
     * Data provider for testProcessMessageCallableIsString
     *
     * @return array
     */
    public function dataProviderTestProcessMessageCallableIsString()
    {
        return [
            ['processReturnNull', InteropProcessor::ACK],
            ['processReturnReject', InteropProcessor::REJECT],
            ['processReturnAck', InteropProcessor::ACK],
        ];
    }

    /**
     * Test processMessage method when callable is string
     *
     * @param string $method The local static method name.
     * @param string $expected The expected result value.
     * @dataProvider dataProviderTestProcessMessageCallableIsString
     * @return void
     */
    public function testProcessMessageCallableIsString($method, $expected)
    {
        $messageBody = [
            "queue" => "default",
            "class" => static::class . '::' . $method,
            "args" => [
                [
                    "data" => ['sample_data' => 'a value', 'key' => md5($method)],
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        static::$lastProcessMessage = null;
        $processor = new Processor();
        $actual = $processor->processMessage($message);
        $this->assertSame($expected, $actual);
        $this->assertSame($message, static::$lastProcessMessage);
    }
}
