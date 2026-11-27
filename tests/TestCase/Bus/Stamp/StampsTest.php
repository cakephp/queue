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

namespace Cake\Queue\Test\TestCase\Bus\Stamp;

use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use Cake\Queue\Bus\StampInterface;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\Result;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Bus\TenantStamp;

class StampsTest extends TestCase
{
    /**
     * @return array<string, array{\Cake\Queue\Bus\StampInterface, array<string, mixed>}>
     */
    public static function stampProvider(): array
    {
        return [
            'received' => [
                new ReceivedStamp('default'),
                ['type' => 'ReceivedStamp', 'v' => 1, 'transport' => 'default'],
            ],
            'handled string' => [
                new HandledStamp('ack'),
                ['type' => 'HandledStamp', 'v' => 1, 'result' => 'ack'],
            ],
            'tenant' => [
                new TenantStamp('tenant:acme'),
                ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
            ],
            'sent' => [
                new SentStamp('abc'),
                ['type' => 'SentStamp', 'v' => 1, 'messageId' => 'abc'],
            ],
            'sent null' => [
                new SentStamp(),
                ['type' => 'SentStamp', 'v' => 1, 'messageId' => null],
            ],
            'skipped' => [
                new SkippedAsDuplicateStamp(),
                ['type' => 'SkippedAsDuplicateStamp', 'v' => 1],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('stampProvider')]
    public function testToArray(StampInterface $stamp, array $expected): void
    {
        $this->assertSame($expected, $stamp->toArray());
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('stampProvider')]
    public function testFromArrayRoundTrip(StampInterface $stamp, array $row): void
    {
        $this->assertEquals($stamp, $stamp::fromArray($row));
    }

    public function testFromArrayIgnoresUnknownKeys(): void
    {
        $stamp = ReceivedStamp::fromArray([
            'type' => 'ReceivedStamp',
            'v' => 99,
            'transport' => 'a',
            'future' => 'ignored',
        ]);

        $this->assertSame('a', $stamp->getTransport());
    }

    public function testConstructRejectsEmptyTransport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty string `transport`');

        new ReceivedStamp('');
    }

    public function testFromArrayRejectsEmptyTransport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty string `transport`');

        ReceivedStamp::fromArray(['type' => 'ReceivedStamp', 'v' => 1, 'transport' => '']);
    }

    public function testHandledStampDegradesObjects(): void
    {
        $stamp = new HandledStamp(Result::requeue('nope'));

        $this->assertSame(
            ['type' => 'HandledStamp', 'v' => 1, 'result' => Result::class],
            $stamp->toArray(),
        );
    }

    /**
     * @return array<string, array{class-string<\Cake\Queue\Bus\StampInterface>, array<string, mixed>}>
     */
    public static function invalidRowProvider(): array
    {
        return [
            'received missing' => [ReceivedStamp::class, []],
            'received empty' => [ReceivedStamp::class, ['transport' => '']],
            'handled missing' => [HandledStamp::class, []],
            'handled non-string' => [HandledStamp::class, ['result' => 42]],
            'sent empty' => [SentStamp::class, ['messageId' => '']],
            'sent non-string' => [SentStamp::class, ['messageId' => 42]],
            'tenant missing' => [TenantStamp::class, []],
            'tenant empty' => [TenantStamp::class, ['tenant' => '']],
        ];
    }

    /**
     * @param class-string<\Cake\Queue\Bus\StampInterface> $class
     * @param array<string, mixed> $row
     */
    #[DataProvider('invalidRowProvider')]
    public function testFromArrayRejectsCorruptRows(string $class, array $row): void
    {
        $this->expectException(InvalidArgumentException::class);

        $class::fromArray($row);
    }
}
