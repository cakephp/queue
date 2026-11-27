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

namespace Cake\Queue\Test\TestCase\Bus;

use ArrayObject;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;
use TestApp\Bus\AnotherStamp;
use TestApp\Bus\CallbackMiddleware;
use TestApp\Bus\FirstStamp;
use TestApp\Bus\RecordingMiddleware;
use TestApp\Bus\TestMessage;

class MessageBusTest extends TestCase
{
    public function testEmptyBusReturnsEnvelopeUnchanged(): void
    {
        $bus = new MessageBus();
        $envelope = new Envelope(new TestMessage('hello'));

        $this->assertSame($envelope, $bus->handle($envelope));
    }

    public function testDispatchWrapsMessageAndStamps(): void
    {
        $bus = new MessageBus();
        $message = new TestMessage('hello');
        $stamp = new FirstStamp();

        $envelope = $bus->dispatch($message, [$stamp]);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertSame([$stamp], $envelope->getStamp(FirstStamp::class));
    }

    public function testDispatchWithEnvelopeAppendsStamps(): void
    {
        $bus = new MessageBus();
        $envelope = new Envelope(new TestMessage('hello'), [new FirstStamp()]);
        $extra = new AnotherStamp();

        $finished = $bus->dispatch($envelope, [$extra]);

        $this->assertSame($envelope->getMessage(), $finished->getMessage());
        $this->assertCount(1, $finished->getStamp(FirstStamp::class));
        $this->assertSame([$extra], $finished->getStamp(AnotherStamp::class));
    }

    public function testMiddlewareCalledInOrder(): void
    {
        $calls = [];
        $bus = new MessageBus([
            new RecordingMiddleware('first', $calls),
            new RecordingMiddleware('second', $calls),
            new RecordingMiddleware('third', $calls),
        ]);

        $bus->dispatch(new TestMessage('hello'));

        $this->assertSame(
            ['first.enter', 'second.enter', 'third.enter', 'third.exit', 'second.exit', 'first.exit'],
            $calls,
        );
    }

    public function testMiddlewareCanAddStamps(): void
    {
        $stamp = new FirstStamp();
        $seen = [];
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use ($stamp): Envelope {
                    return $stack->next()->handle($envelope->withStamp($stamp), $stack);
                },
            ),
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$seen): Envelope {
                    $seen = $envelope->getStamp(FirstStamp::class);

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
        ]);

        $bus->dispatch(new TestMessage('hello'));

        $this->assertSame([$stamp], $seen);
    }

    public function testMiddlewareCanReplaceEnvelope(): void
    {
        $replacement = new TestMessage('changed');
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use ($replacement): Envelope {
                    return $stack->next()->handle(new Envelope($replacement), $stack);
                },
            ),
        ]);

        $finished = $bus->dispatch(new TestMessage('hello'));

        $this->assertSame($replacement, $finished->getMessage());
    }

    public function testShortCircuit(): void
    {
        $secondCalled = false;
        $expected = new Envelope(new TestMessage('short'));
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use ($expected): Envelope {
                    return $expected;
                },
            ),
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$secondCalled): Envelope {
                    $secondCalled = true;

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
        ]);

        $finished = $bus->dispatch(new TestMessage('hello'));

        $this->assertSame($expected, $finished);
        $this->assertFalse($secondCalled);
    }

    public function testExceptionPropagation(): void
    {
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                    throw new RuntimeException('Middleware failure');
                },
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Middleware failure');

        $bus->dispatch(new TestMessage('hello'));
    }

    public function testInvalidMiddlewareThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        new MessageBus([new TestMessage('not middleware')]);
    }

    public function testConstructorAcceptsTraversable(): void
    {
        $calls = [];
        $middleware = (static function () use (&$calls): iterable {
            yield new RecordingMiddleware('first', $calls);
            yield new RecordingMiddleware('second', $calls);
        })();

        $bus = new MessageBus($middleware);
        $bus->dispatch(new TestMessage('hello'));

        $this->assertSame(
            ['first.enter', 'second.enter', 'second.exit', 'first.exit'],
            $calls,
        );
    }

    public function testConstructorAcceptsArrayObject(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));
        $bus = new MessageBus(new ArrayObject());

        $this->assertSame($envelope, $bus->handle($envelope));
    }

    public function testBusIsReusable(): void
    {
        $count = 0;
        $bus = new MessageBus([
            new CallbackMiddleware(
                static function (Envelope $envelope, BusStackInterface $stack) use (&$count): Envelope {
                    $count++;

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
        ]);

        $first = $bus->dispatch(new TestMessage('one'));
        $second = $bus->dispatch(new TestMessage('two'));

        $this->assertSame(2, $count);
        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->getMessage(), $second->getMessage());
        $this->assertSame('one', $first->getMessage()->text);
        $this->assertSame('two', $second->getMessage()->text);
    }
}
