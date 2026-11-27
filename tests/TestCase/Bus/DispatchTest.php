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

use Cake\Queue\Bus\Dispatch;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dto\OrderDto;
use TestApp\Job\LogToDebugJob;

class DispatchTest extends TestCase
{
    public function testToBody(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'emails',
            'default',
            30,
            3600,
            'high',
            ['custom' => 'kept'],
        );

        $this->assertSame(
            [
                'class' => [LogToDebugJob::class, 'execute'],
                'args' => [['id' => 7]],
                'data' => ['id' => 7],
                'requeueOptions' => [
                    'config' => 'default',
                    'priority' => 'high',
                    'queue' => 'emails',
                ],
            ],
            $dispatch->toBody(),
        );
    }

    public function testToBodyWithDtoClass(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            OrderDto::class,
            'default',
            'default',
        );

        $body = $dispatch->toBody();

        $this->assertSame(OrderDto::class, $body['dtoClass']);
    }

    public function testToBodyWithoutDtoClass(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'default',
            'default',
        );

        $this->assertArrayNotHasKey('dtoClass', $dispatch->toBody());
    }

    public function testWithersReturnChangedCopies(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'emails',
            'invoices',
            null,
            null,
            null,
            [],
        );

        $queued = $dispatch->withQueue('other');
        $this->assertNotSame($dispatch, $queued);
        $this->assertSame('other', $queued->getQueue());
        $this->assertSame('emails', $dispatch->getQueue());

        $delayed = $dispatch->withDelay(30);
        $this->assertSame(30, $delayed->getDelay());
        $this->assertNull($dispatch->getDelay());

        $expiring = $dispatch->withExpires(3600);
        $this->assertSame(3600, $expiring->getExpires());

        $prioritized = $dispatch->withPriority('high');
        $this->assertSame('high', $prioritized->getPriority());
        $this->assertNull($dispatch->getPriority());

        $this->assertSame($dispatch->getClass(), $queued->getClass());
        $this->assertSame($dispatch->getMethod(), $queued->getMethod());
        $this->assertSame($dispatch->getData(), $queued->getData());
        $this->assertSame($dispatch->getDtoClass(), $queued->getDtoClass());
        $this->assertSame($dispatch->getConfigName(), $queued->getConfigName());
        $this->assertSame($dispatch->getMetadata(), $queued->getMetadata());
        $this->assertSame('other', $queued->toBody()['requeueOptions']['queue']);
    }

    public function testGetters(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            OrderDto::class,
            'emails',
            'invoices',
            30,
            3600,
            'high',
            ['custom' => 'kept'],
        );

        $this->assertSame(LogToDebugJob::class, $dispatch->getClass());
        $this->assertSame('execute', $dispatch->getMethod());
        $this->assertSame(['id' => 7], $dispatch->getData());
        $this->assertSame(OrderDto::class, $dispatch->getDtoClass());
        $this->assertSame('emails', $dispatch->getQueue());
        $this->assertSame('invoices', $dispatch->getConfigName());
        $this->assertSame(30, $dispatch->getDelay());
        $this->assertSame(3600, $dispatch->getExpires());
        $this->assertSame('high', $dispatch->getPriority());
        $this->assertSame(['custom' => 'kept'], $dispatch->getMetadata());
    }

    /**
     * @return array<string, array{string, string, string, string, int|null, int|null, string|null}>
     */
    public static function invalidConstructorProvider(): array
    {
        $valid = [LogToDebugJob::class, 'execute', 'emails', 'invoices', 30, 3600, 'high'];

        return [
            'empty class' => ['', ...array_slice($valid, 1)],
            'empty method' => [$valid[0], '', ...array_slice($valid, 2)],
            'empty queue' => [...array_slice($valid, 0, 2), '', ...array_slice($valid, 3)],
            'empty config' => [...array_slice($valid, 0, 3), '', ...array_slice($valid, 4)],
            'negative delay' => [...array_slice($valid, 0, 4), -1, ...array_slice($valid, 5)],
            'negative expires' => [...array_slice($valid, 0, 5), -1, ...array_slice($valid, 6)],
            'empty priority' => [...array_slice($valid, 0, 6), ''],
        ];
    }

    #[DataProvider('invalidConstructorProvider')]
    public function testConstructRejectsInvalidValues(
        string $class,
        string $method,
        string $queue,
        string $config,
        ?int $delay,
        ?int $expires,
        ?string $priority,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new Dispatch($class, $method, ['id' => 7], null, $queue, $config, $delay, $expires, $priority);
    }

    public function testWithersRejectInvalidValues(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'emails',
            'invoices',
        );

        $this->expectException(InvalidArgumentException::class);

        $dispatch->withQueue('');
    }

    public function testWithersRejectInvalidDelayExpiresPriority(): void
    {
        $dispatch = new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'emails',
            'invoices',
        );

        try {
            $dispatch->withDelay(-1);
            $this->fail('Negative delay must throw.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->assertStringContainsString('delay', $invalidArgumentException->getMessage());
        }

        try {
            $dispatch->withExpires(-1);
            $this->fail('Negative expires must throw.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->assertStringContainsString('expires', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('priority');

        $dispatch->withPriority('');
    }

    public function testConstructRejectsReservedStampsMetadataKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved for the bus');

        new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            null,
            'emails',
            'invoices',
            null,
            null,
            null,
            ['stamps' => []],
        );
    }

    public function testConstructRejectsMissingDtoClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 7],
            'App\\Missing\\Nope',
            'emails',
            'invoices',
        );
    }

    public function testConstructRejectsMissingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new Dispatch(
            'App\\Missing\\Nope',
            'execute',
            ['id' => 7],
            null,
            'emails',
            'invoices',
        );
    }
}
