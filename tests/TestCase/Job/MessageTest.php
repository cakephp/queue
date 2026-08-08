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
use TestApp\Dto\OrderDto;
use TestApp\Dto\OrderItemDto;
use TestApp\Dto\UserDto;
use TestApp\WelcomeMailer;

class MessageTest extends TestCase
{
    /**
     * Test getters methods
     *
     * @return void
     */
    public function testConstructorAndGetters()
    {
        $callable = [WelcomeMailer::class, 'welcome'];
        $time = 'sample data ' . time();
        $id = 7;
        $data = ['id' => $id, 'time' => $time];
        $parsedBody = [
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
        $this->assertSame(3, $message->getMaxAttempts());
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
        $callable = [WelcomeMailer::class, 'welcome'];
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
     * Test that a DTO dispatched with the message is hydrated on the receiving side.
     *
     * @return void
     */
    public function testGetDto()
    {
        $parsedBody = [
            'class' => [WelcomeMailer::class, 'welcome'],
            'data' => [
                'id' => 7,
                'customer' => 'Acme Corp',
                'items' => [
                    ['sku' => 'SKU-1', 'quantity' => 2],
                    ['sku' => 'SKU-2', 'quantity' => 1],
                ],
            ],
            'dtoClass' => OrderDto::class,
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage((string)json_encode($parsedBody));
        $message = new Message($originalMessage, $context);

        $this->assertSame(OrderDto::class, $message->getDtoClass());

        $dto = $message->getDto();
        $this->assertInstanceOf(OrderDto::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertSame('Acme Corp', $dto->customer);
        $this->assertCount(2, $dto->items);
        $this->assertInstanceOf(OrderItemDto::class, $dto->items[0]);
        $this->assertSame('SKU-1', $dto->items[0]->sku);
        $this->assertSame(1, $dto->items[1]->quantity);

        // The DTO is only hydrated once.
        $this->assertSame($dto, $message->getDto());

        // The raw data is still accessible as an array.
        $this->assertSame($parsedBody['data'], $message->getArgument());
        $this->assertSame(7, $message->getArgument('id'));
    }

    /**
     * Test that DTOs using a `createFromArray()` factory are supported.
     *
     * @return void
     */
    public function testGetDtoWithCreateFromArray()
    {
        $parsedBody = [
            'class' => [WelcomeMailer::class, 'welcome'],
            'data' => [
                'id' => 3,
                'username' => 'markstory',
            ],
            'dtoClass' => UserDto::class,
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage((string)json_encode($parsedBody));
        $message = new Message($originalMessage, $context);

        $dto = $message->getDto();
        $this->assertInstanceOf(UserDto::class, $dto);
        $this->assertSame(3, $dto->id);
        $this->assertSame('markstory', $dto->username);
    }

    /**
     * Test that messages without a DTO class do not expose a DTO.
     *
     * @return void
     */
    public function testGetDtoWithoutDtoClass()
    {
        $parsedBody = [
            'class' => [WelcomeMailer::class, 'welcome'],
            'data' => ['id' => 7],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage((string)json_encode($parsedBody));
        $message = new Message($originalMessage, $context);

        $this->assertNull($message->getDtoClass());
        $this->assertNull($message->getDto());
        $this->assertSame(['id' => 7], $message->getArgument());
    }

    /**
     * Test that a `dtoClass` referencing a class that no longer exists at
     * consume time is treated the same as no DTO at all, rather than crashing.
     *
     * @return void
     */
    public function testGetDtoWithUnresolvableDtoClass()
    {
        $parsedBody = [
            'class' => [WelcomeMailer::class, 'welcome'],
            'data' => ['id' => 7],
            'dtoClass' => 'TestApp\Dto\DoesNotExist',
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage((string)json_encode($parsedBody));
        $message = new Message($originalMessage, $context);

        $this->assertNull($message->getDtoClass());
        $this->assertNull($message->getDto());
        $this->assertSame(['id' => 7], $message->getArgument());
    }

    /**
     * Test that invalid classes cannot be made into callables.
     *
     * @return void
     */
    public function testGetCallableInvalidClass()
    {
        $parsedBody = [
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
            'class' => [WelcomeMailer::class, 'trash', 'oops'],
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
