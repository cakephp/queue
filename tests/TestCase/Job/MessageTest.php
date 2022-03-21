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
namespace Cake\Queue\Test\TestCase\Job;

use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Closure;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Error;
use RuntimeException;

class MessageTest extends TestCase
{
    /**
     * Test getters methods
     *
     * @return void
     */
    public function testConstructorAndGetters()
    {
        $callable = ['TestApp\WelcomeMailer', 'welcome'];
        $time = 'sample data ' . time();
        $id = 7;
        $data = compact('id', 'time');
        $parsedBody = [
            'queue' => 'default',
            'class' => $callable,
            'data' => $data,
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $this->assertSame($context, $message->getContext());
        $this->assertSame($originalMessage, $message->getOriginalMessage());
        $this->assertSame($parsedBody, $message->getParsedBody());
        $this->assertInstanceOf(Closure::class, $message->getCallable());
        $this->assertSame($data, $message->getArgument());
        $this->assertSame($id, $message->getArgument('id'));
        $this->assertSame($time, $message->getArgument('time', 'ignore_this'));
        $this->assertSame('should_use_this', $message->getArgument('unknown', 'should_use_this'));
        $this->assertNull($message->getArgument('unknown'));
        $actualJson = json_encode($message);
        $this->assertSame($messageBody, $actualJson);
        $actualToStringValue = (string)$message;
        $this->assertSame($messageBody, $actualToStringValue);
    }

    /**
     * Test legacy arguments
     *
     * @return void
     */
    public function testLegacyArguments()
    {
        $callable = ['TestApp\WelcomeMailer', 'welcome'];
        $args = [
            'first' => 1,
            'second' => 'two',
        ];
        $parsedBody = [
            'queue' => 'default',
            'class' => $callable,
            'args' => [$args],
        ];

        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage(json_encode($parsedBody));
        $message = new Message($originalMessage, $context);

        $this->assertSame($args, $message->getArgument());
        $this->assertSame(1, $message->getArgument('first'));
        $this->assertSame('two', $message->getArgument('second', 'ignore_this'));
        $this->assertSame('no third argument', $message->getArgument('third', 'no third argument'));
    }

    /**
     * Test that invalid classes cannot be made into callables.
     *
     * @return void
     */
    public function testGetCallableInvalidClass()
    {
        $parsedBody = [
            'queue' => 'default',
            'class' => ['Trash', 'trash'],
            'args' => [],
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $this->expectException(Error::class);
        $message->getCallable();
    }

    /**
     * Test that invalid classes cannot be made into callables.
     *
     * @return void
     */
    public function testGetCallableInvalidType()
    {
        $parsedBody = [
            'queue' => 'default',
            'class' => ['TestApp\WelcomeMailer', 'trash', 'oops'],
            'args' => [],
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $this->expectException(RuntimeException::class);
        $message->getCallable();
    }
}
