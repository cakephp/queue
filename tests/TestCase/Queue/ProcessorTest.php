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

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
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
            [InteropProcessor::ACK, InteropProcessor::ACK, 'Message processed sucessfully', 'Processor.message.success'],
            [InteropProcessor::REJECT, InteropProcessor::REJECT, 'Message processed with rejection', 'Processor.message.reject'],
            [InteropProcessor::REQUEUE, InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
            ['anything_else', InteropProcessor::REQUEUE, 'Message processed with failure, requeuing', 'Processor.message.failure'],
        ];
    }

    /**
     * Test process method
     *
     * @param string $processMessageReturn The processMessage return value.
     * @param string $expected The expected process result.
     * @param string $logMessage The log message based on process result.
     * @param string $dispacthedEvent The dispatched event based on process result.
     *
     * @dataProvider dataProviderTestProcess
     * @return void
     */
    public function testProcess($processMessageReturn, $expected, $logMessage, $dispacthedEvent)
    {
        $messageBody = [
            "queue" => "default",
            "class" => ["Queue\\Job\\EventJob", "execute"],
            "args" => [
                [
                    "className" => "Cake\\Event\\Event",
                    "eventName" => 'TestCase.testProcess',
                    "data" => ['sample_data' => 'a value'],
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        $logger = $this->getMockBuilder(NullLogger::class)
            ->setMethods(['log'])
            ->getMock();
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo(LogLevel::DEBUG),
                $logMessage
            );
        $processor = $this->getMockBuilder(Processor::class)
            ->setConstructorArgs([$logger])
            ->setMethods(['processMessage', 'dispatchEvent'])
            ->getMock();

        $event = new Event('Processor.message.seen', $processor, ['queueMessage' => $queueMessage]);
        $processor->expects($this->at(0))
            ->method('dispatchEvent')
            ->with(
                $this->equalTo('Processor.message.seen'),
                $this->equalTo(['queueMessage' => $queueMessage])
            )
            ->willReturn($event);

        $eventData = ['message' => $message];
        $event = new Event('Processor.message.start', $processor, $eventData);
        $processor->expects($this->at(1))
            ->method('dispatchEvent')
            ->with(
                $this->equalTo('Processor.message.start'),
                $this->equalTo($eventData)
            )
            ->willReturn($event);

        $processor->expects($this->at(2))
            ->method('processMessage')
            ->with($this->equalTo($message))
            ->willReturn($processMessageReturn);

        $eventData = ['message' => $message];
        $event = new Event($dispacthedEvent, $processor, $eventData);
        $processor->expects($this->at(3))
        ->method('dispatchEvent')
        ->with(
            $this->equalTo($dispacthedEvent),
            $this->equalTo($eventData)
        )
        ->willReturn($event);

        $actual = $processor->process($queueMessage, $context);
        $this->assertEquals($expected, $actual);
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
        $message = new Message($queueMessage, $context);
        $logger = $this->getMockBuilder(NullLogger::class)
            ->setMethods(['log'])
            ->getMock();
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo(LogLevel::DEBUG),
                'Invalid callable for message. Rejecting message from queue.'
            );
        $processor = $this->getMockBuilder(Processor::class)
            ->setConstructorArgs([$logger])
            ->setMethods(['processMessage', 'dispatchEvent'])
            ->getMock();

        $event = new Event('Processor.message.seen', $processor, ['queueMessage' => $queueMessage]);
        $processor->expects($this->at(0))
            ->method('dispatchEvent')
            ->with(
                $this->equalTo('Processor.message.seen'),
                $this->equalTo(['queueMessage' => $queueMessage])
            )
            ->willReturn($event);

        $eventData = ['message' => $message];
        $event = new Event('Processor.message.invalid', $processor, $eventData);
        $processor->expects($this->at(1))
            ->method('dispatchEvent')
            ->with(
                $this->equalTo('Processor.message.invalid'),
                $this->equalTo($eventData)
            )
            ->willReturn($event);

        $processor->expects($this->never())
            ->method('processMessage');

        $actual = $processor->process($queueMessage, $context);
        $expected = InteropProcessor::REJECT;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test processMessage method.
     *
     * @return void
     */
    public function testProcessMessage()
    {
        $eventName = 'TestCase.testProcessMessage';
        $calledEventCallback = false;
        EventManager::instance()->on($eventName, function (Event $event) use (&$calledEventCallback) {
            $calledEventCallback = true;
        });
        $messageBody = [
            "queue" => "default",
            "class" => ["Queue\\Job\\EventJob", "execute"],
            "args" => [
                [
                    "className" => "Cake\\Event\\Event",
                    "eventName" => 'TestCase.testProcessMessage',
                    "data" => ['sample_data' => 'a value'],
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $queueMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($queueMessage, $context);
        $processor = new Processor();
        $actual = $processor->processMessage($message);
        $expected = InteropProcessor::ACK;
        $this->assertSame($expected, $actual);
        $this->assertTrue($calledEventCallback);
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     *
     * @return null
     */
    public static function jobProcessMessageCallableIsStringReturnNull(Message $message)
    {
        static::$lastProcessMessage = $message;

        return null;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     *
     * @return null
     */
    public static function jobProcessMessageCallableIsStringReturnReject(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::REJECT;
    }

    /**
     * Job to be used in test testProcessMessageCallableIsString
     *
     * @param \Queue\Queue\Message $message The message to process
     *
     * @return null
     */
    public static function jobProcessMessageCallableIsStringReturnAck(Message $message)
    {
        static::$lastProcessMessage = $message;

        return InteropProcessor::ACK;
    }

    /**
     * Data provider for testProcessMessageCallableIsString
     *
     * @return array
     */
    public function dataProviderTestProcessMessageCallableIsString()
    {
        return [
            ['jobProcessMessageCallableIsStringReturnNull', InteropProcessor::ACK],
            ['jobProcessMessageCallableIsStringReturnReject', InteropProcessor::REJECT],
            ['jobProcessMessageCallableIsStringReturnAck', InteropProcessor::ACK],
        ];
    }

    /**
     * Test processMessage method when callable is string
     *
     * @param string $method The local static method name.
     * @param string $expected The expected result value.
     *
     * @dataProvider dataProviderTestProcessMessageCallableIsString
     * @return void
     */
    public function testProcessMessageCallableIsString($method, $expected)
    {
        $messageBody = [
            "queue" => "default",
            "class" => '\\Queue\\Test\\TestCase\\Queue\\ProcessorTest::' . $method,
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
