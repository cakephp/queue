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
namespace Queue\Test\TestCase\Job;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Queue\Job\EventJob;
use Queue\Job\Message;
use Queue\Queue\Processor;

class EventJobTest extends TestCase
{
    protected $data = ["order" => ["id" => 1000,"paid" => true], 'username' => 'john.doe'];

    /**
     * Test execute method.
     *
     * @return void
     */
    public function testExecuteOkayNormal()
    {
        $eventName = "TestCase.Orders.placed";
        EventManager::instance()->on($eventName, function (Event $event) use (&$calledEventCallback) {
            $calledEventCallback = true;
            $this->assertSame($this->data, $event->getData());
        });
        $message = $this->createMessage($eventName);
        $job = new EventJob();
        $actual = $job->execute($message);
        $this->assertSame(Processor::ACK, $actual);
        $this->assertTrue($calledEventCallback);
    }

    /**
     * Test execute method when event class is not valid.
     *
     * @return void
     */
    public function testExecuteInvalidEventClass()
    {
        $eventName = "TestCase.testExecuteInvalidEventClass";
        $calledEventCallback = false;
        EventManager::instance()->on($eventName, function (Event $event) use (&$calledEventCallback) {
            $calledEventCallback = true;
            $event->stopPropagation();
        });
        $message = $this->createMessage($eventName);
        $job = new EventJob();
        $actual = $job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
        $this->assertTrue($calledEventCallback);
    }

    /**
     * Test execute method when event is stopped.
     *
     * @return void
     */
    public function testExecuteEventStopped()
    {
        $eventName = "TestCase.testExecuteEventStopped";
        $calledEventCallback = false;
        EventManager::instance()->on($eventName, function (Event $event) use (&$calledEventCallback) {
            $calledEventCallback = true;
        });
        $message = $this->createMessage($eventName, '\\Invalid\\Event');
        $job = new EventJob();
        $actual = $job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
        $this->assertFalse($calledEventCallback);
    }

    /**
     * Test execute method when event listener has a custom return.
     *
     * @return void
     */
    public function testExecuteCustomReturn()
    {
        $eventName = "TestCase.testExecuteCustomReturn";
        $calledEventCallback = false;
        EventManager::instance()->on($eventName, function (Event $event) use (&$calledEventCallback) {
            $calledEventCallback = true;
            $event->setResult(['return' => 'my.custom.return']);
        });
        $message = $this->createMessage($eventName);
        $job = new EventJob();
        $actual = $job->execute($message);
        $this->assertSame('my.custom.return', $actual);
        $this->assertTrue($calledEventCallback);
    }

    /**
     * Create a simple message for testing.
     *
     * @param string $eventName The event name.
     * @param string $className The event class name.
     *
     * @return \Queue\Job\Message
     */
    protected function createMessage(string $eventName, $className = null): Message
    {
        $messageBody = [
            "queue" => "default",
            "class" => ["Queue\\Job\\EventJob", "execute"],
            "args" => [
                [
                    "className" => $className ?? "Cake\\Event\\Event",
                    "eventName" => $eventName,
                    "data" => $this->data,
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($originalMessage, $context);

        return $message;
    }
}
