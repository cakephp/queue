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

use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;

class MessageTest extends TestCase
{
    /**
     * Test getters methods
     *
     * @return void
     */
    public function testConstructorAndGetters()
    {
        $callable = ['App\\Job\\ExampleJob','execute'];
        $data = 'sample data ' . time();
        $id = 7;
        $args = compact('id', 'data');
        $parsedBody = [
            'queue' => 'default',
            'class' => $callable,
            'args' => [$args],
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $this->assertSame($context, $message->getContext());
        $this->assertSame($originalMessage, $message->getOriginalMessage());
        $this->assertSame($parsedBody, $message->getParsedBody());
        $this->assertSame($callable, $message->getCallable());
        $this->assertSame($args, $message->getArgument());
        $this->assertSame($id, $message->getArgument('id'));
        $this->assertSame($data, $message->getArgument('data', 'ignore_this'));
        $this->assertSame('should_use_this', $message->getArgument('unknown', 'should_use_this'));
        $this->assertNull($message->getArgument('unknown'));
        $actualJson = json_encode($message);
        $this->assertSame($messageBody, $actualJson);
        $actualToStringValue = (string)$message;
        $this->assertSame($messageBody, $actualToStringValue);
    }
}
