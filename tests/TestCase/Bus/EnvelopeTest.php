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

use Cake\Queue\Bus\Envelope;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use ReflectionProperty;
use stdClass;
use TestApp\Bus\ChildStamp;
use TestApp\Bus\FirstStamp;
use TestApp\Bus\SecondStamp;
use TestApp\Bus\StampGroupInterface;
use TestApp\Bus\TestMessage;
use TestApp\Bus\UnknownStamp;

class EnvelopeTest extends TestCase
{
    public function testConstructGroupsStampsByClass(): void
    {
        $first = new FirstStamp();
        $second = new SecondStamp();
        $message = new TestMessage('hello');

        $envelope = new Envelope($message, [$first, $second]);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertSame([$first], $envelope->getStamp(FirstStamp::class));
        $this->assertSame([$second], $envelope->getStamp(SecondStamp::class));
        $this->assertSame($first, $envelope->getLastStamp(FirstStamp::class));
    }

    public function testConstructRejectsNonStampValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        new Envelope(new TestMessage('hello'), [new TestMessage('not a stamp')]);
    }

    public function testWithStampRejectsNonStampValues(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));

        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        $envelope->withStamp(new TestMessage('not a stamp'));
    }

    public function testGetLastStampIgnoresEmptyBucket(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));

        $property = new ReflectionProperty(Envelope::class, 'stamps');
        $property->setValue($envelope, [FirstStamp::class => []]);

        $this->assertNull($envelope->getLastStamp(FirstStamp::class));
    }

    public function testWithStampReturnsNewInstance(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));

        $next = $envelope->withStamp(new FirstStamp());

        $this->assertNotSame($envelope, $next);
        $this->assertSame([], $envelope->getStamp(FirstStamp::class));
        $this->assertCount(1, $next->getStamp(FirstStamp::class));
    }

    public function testWithStampAccumulatesStampsOfSameClass(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [$first = new FirstStamp()]);

        $next = $envelope->withStamp($second = new FirstStamp());

        $this->assertSame([$first], $envelope->getStamp(FirstStamp::class));
        $this->assertSame([$first, $second], $next->getStamp(FirstStamp::class));
        $this->assertSame($second, $next->getLastStamp(FirstStamp::class));
    }

    public function testWithoutStamp(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [
            new FirstStamp(),
            new FirstStamp(),
            new SecondStamp(),
        ]);

        $next = $envelope->withoutStamp(FirstStamp::class);

        $this->assertNotSame($envelope, $next);
        $this->assertCount(2, $envelope->getStamp(FirstStamp::class));
        $this->assertSame([], $next->getStamp(FirstStamp::class));
        $this->assertCount(1, $next->getStamp(SecondStamp::class));
    }

    public function testWithoutStampWithUnknownClass(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [new FirstStamp()]);

        $next = $envelope->withoutStamp(SecondStamp::class);

        $this->assertNotSame($envelope, $next);
        $this->assertEquals($envelope, $next);
    }

    public function testWithoutStampOfType(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [
            new FirstStamp(),
            new ChildStamp(),
            new SecondStamp(),
        ]);

        $next = $envelope->withoutStampOfType(StampGroupInterface::class);

        $this->assertCount(0, $next->getStamp(FirstStamp::class));
        $this->assertCount(0, $next->getStamp(ChildStamp::class));
        $this->assertCount(1, $next->getStamp(SecondStamp::class));

        $exact = $envelope->withoutStampOfType(FirstStamp::class);

        $this->assertCount(0, $exact->getStamp(FirstStamp::class));
        $this->assertCount(1, $exact->getStamp(ChildStamp::class));
        $this->assertCount(1, $exact->getStamp(SecondStamp::class));
    }

    public function testWithoutStampOfTypeWithUnknownClass(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [new FirstStamp()]);

        $next = $envelope->withoutStampOfType(UnknownStamp::class);

        $this->assertNotSame($envelope, $next);
        $this->assertEquals($envelope, $next);
    }

    public function testGetLastStamp(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [$first = new FirstStamp()]);

        $this->assertSame($first, $envelope->getLastStamp(FirstStamp::class));
        $this->assertNull($envelope->getLastStamp(SecondStamp::class));
        $this->assertNull($envelope->getLastStamp(UnknownStamp::class));
    }

    public function testGetLastStampReturnsMostRecentStamp(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));
        $envelope = $envelope->withStamp($first = new FirstStamp())->withStamp($second = new FirstStamp());

        $this->assertSame($second, $envelope->getLastStamp(FirstStamp::class));
        $this->assertNotSame($first, $envelope->getLastStamp(FirstStamp::class));
    }

    public function testGetStamps(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));
        $envelope = $envelope
            ->withStamp($first = new FirstStamp())
            ->withStamp($second = new SecondStamp());

        $stamps = $envelope->getStamps();

        $this->assertArrayHasKey(FirstStamp::class, $stamps);
        $this->assertArrayHasKey(SecondStamp::class, $stamps);
        $this->assertSame([$first], $envelope->getStamp(FirstStamp::class));
        $this->assertSame([$second], $envelope->getStamp(SecondStamp::class));
    }

    public function testGetStampsWithUnknownClass(): void
    {
        $envelope = new Envelope(new TestMessage('hello'));

        $this->assertSame([], $envelope->getStamp(UnknownStamp::class));
    }

    public function testWrapWithMessage(): void
    {
        $message = new TestMessage('hello');
        $stamp = new FirstStamp();

        $envelope = Envelope::wrap($message, [$stamp]);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertSame([$stamp], $envelope->getStamp(FirstStamp::class));
    }

    public function testWithMessageKeepsStamps(): void
    {
        $original = new TestMessage('hello');
        $replacement = new TestMessage('changed');
        $envelope = new Envelope($original, [new FirstStamp()]);

        $next = $envelope->withMessage($replacement);

        $this->assertNotSame($envelope, $next);
        $this->assertSame($replacement, $next->getMessage());
        $this->assertSame($original, $envelope->getMessage());
        $this->assertCount(1, $next->getStamp(FirstStamp::class));
    }

    public function testWrapWithMessageWithoutStamps(): void
    {
        $message = new TestMessage('hello');

        $envelope = Envelope::wrap($message);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertSame([], $envelope->getStamps());
    }

    public function testWrapWithEnvelope(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [new FirstStamp()]);

        $next = Envelope::wrap($envelope, [new SecondStamp()]);

        $this->assertSame($envelope->getMessage(), $next->getMessage());
        $this->assertCount(1, $next->getStamp(FirstStamp::class));
        $this->assertCount(1, $next->getStamp(SecondStamp::class));
        $this->assertCount(0, $envelope->getStamp(SecondStamp::class));
    }

    public function testWrapRejectsNonStamps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        Envelope::wrap(new TestMessage('hello'), [new stdClass()]);
    }
}
