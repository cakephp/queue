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

namespace Cake\Queue\Test\TestCase\Bus\Middleware;

use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Middleware\UniqueCheckMiddleware;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use TestApp\Bus\CallbackMiddleware;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\UniqueJob;

class UniqueCheckMiddlewareTest extends TestCase
{
    use QueueTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        QueueManager::setConfig('test-unique', [
            'url' => 'null:',
            'uniqueCache' => ['engine' => 'File'],
        ]);
        $cacheKey = QueueManager::getConfig('test-unique')['uniqueCacheKey'] ?? null;
        if ($cacheKey !== null) {
            Cache::clear($cacheKey);
        }
    }

    public function testNonUniquePassesThrough(): void
    {
        $called = 0;
        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static function ($envelope, $stack) use (&$called) {
                    $called++;

                    return $stack->next()->handle($envelope, $stack);
                },
            ),
        ]);

        $envelope = new Envelope($this->dispatch(LogToDebugJob::class));
        $finished = $bus->handle($envelope);

        $this->assertSame(1, $called);
        $this->assertSame($envelope, $finished);
    }

    public function testUnknownJobClassThrowsInvalidArgumentException(): void
    {
        $bus = new MessageBus([new UniqueCheckMiddleware()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $bus->handle(new Envelope($this->dispatch('NotARealJob')));
    }

    public function testFirstPushPassesAndAddsCacheEntry(): void
    {
        $uniqueId = QueueManager::getUniqueId(UniqueJob::class, 'execute', []);
        $cacheKey = $this->cacheKey('test-unique');
        $this->assertEmpty(Cache::read($uniqueId, $cacheKey));

        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static fn($envelope, $stack) => $stack->next()->handle($envelope, $stack)->withStamp(new SentStamp('sent')),
            ),
        ]);
        $bus->handle(new Envelope($this->dispatch(UniqueJob::class)));

        $this->assertTrue((bool)Cache::read($uniqueId, $cacheKey));
    }

    public function testDuplicateShortCircuits(): void
    {
        $called = 0;
        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static function ($envelope, $stack) use (&$called) {
                    $called++;

                    return $stack->next()->handle(
                        $envelope->withStamp(new SentStamp('sent')),
                        $stack,
                    );
                },
            ),
        ]);

        $envelope = new Envelope($this->dispatch(UniqueJob::class));
        $bus->handle($envelope);
        $finished = $bus->handle($envelope);

        $this->assertSame(1, $called);
        $this->assertNotSame($envelope, $finished);
        $this->assertNull($finished->getLastStamp(SentStamp::class));
        $this->assertNotNull($finished->getLastStamp(SkippedAsDuplicateStamp::class));
    }

    public function testCacheEntryAddedOnlyAfterSend(): void
    {
        $uniqueId = QueueManager::getUniqueId(UniqueJob::class, 'execute', []);
        $cacheKey = $this->cacheKey('test-unique');

        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static function ($envelope, $stack) use ($uniqueId, $cacheKey) {
                    // During send the entry must not exist yet (check-then-act order).
                    if (Cache::read($uniqueId, $cacheKey)) {
                        throw new InvalidArgumentException('Cache entry added before send.');
                    }

                    // Terminal simulates SendMiddleware: SentStamp only on success.
                    return $stack->next()->handle($envelope, $stack)->withStamp(new SentStamp('sent'));
                },
            ),
        ]);

        $bus->handle(new Envelope($this->dispatch(UniqueJob::class)));

        $this->assertTrue((bool)Cache::read($uniqueId, $cacheKey));
    }

    public function testSwallowedDispatchDoesNotPoisonCache(): void
    {
        $uniqueId = QueueManager::getUniqueId(UniqueJob::class, 'execute', []);
        $cacheKey = $this->cacheKey('test-unique');

        $called = 0;
        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static function ($envelope, $stack) use (&$called) {
                    $called++;

                    return $envelope;
                },
            ),
        ]);

        $envelope = new Envelope($this->dispatch(UniqueJob::class));
        $bus->handle($envelope);
        $bus->handle($envelope);

        $this->assertSame(2, $called);
        $this->assertEmpty(Cache::read($uniqueId, $cacheKey));
    }

    public function testMissingUniqueCacheThrows(): void
    {
        QueueManager::setConfig('no-cache', ['url' => 'null:']);

        $bus = new MessageBus([new UniqueCheckMiddleware()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('::$shouldBeUnique is set to `true` but `uniqueCache` configuration is missing.');

        $bus->handle(new Envelope($this->dispatch(UniqueJob::class, 'no-cache')));
    }

    public function testDuplicateLogsDebugMessage(): void
    {
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);
        QueueManager::setConfig('test-unique-logged', [
            'url' => 'null:',
            'uniqueCache' => ['engine' => 'File'],
            'logger' => 'debug',
        ]);

        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static fn($envelope, $stack) => $stack->next()->handle($envelope, $stack)->withStamp(new SentStamp('sent')),
            ),
        ]);
        $envelope = new Envelope($this->dispatch(UniqueJob::class, 'test-unique-logged'));
        $bus->handle($envelope);
        $bus->handle($envelope);

        /** @var \Cake\Log\Engine\ArrayLog $debugLog */
        $debugLog = Log::engine('debug');
        $matches = 0;
        $lines = $debugLog->read();
        $last = end($lines);
        foreach ($lines as $line) {
            if (str_contains($line, 'already exists on the queue. This push will be ignored.')) {
                $matches++;
            }
        }

        $this->assertSame(1, $matches);
        $this->assertStringContainsString('already exists on the queue', (string)$last);
        Log::drop('debug');
    }

    public function testDifferentPayloadsAreBothSent(): void
    {
        $called = 0;
        $bus = new MessageBus([
            new UniqueCheckMiddleware(),
            new CallbackMiddleware(
                static function ($envelope, $stack) use (&$called) {
                    $called++;

                    return $stack->next()->handle(
                        $envelope->withStamp(new SentStamp('sent')),
                        $stack,
                    );
                },
            ),
        ]);

        $first = new Envelope(new Dispatch(UniqueJob::class, 'execute', ['id' => 1], null, 'default', 'test-unique'));
        $second = new Envelope(new Dispatch(UniqueJob::class, 'execute', ['id' => 2], null, 'default', 'test-unique'));
        $bus->handle($first);
        $finished = $bus->handle($second);

        $this->assertSame(2, $called);
        $this->assertNotNull($finished->getLastStamp(SentStamp::class));
        $this->assertNull($finished->getLastStamp(SkippedAsDuplicateStamp::class));
    }

    public function testRequiresDispatchMessage(): void
    {
        $bus = new MessageBus([new UniqueCheckMiddleware()]);

        $this->expectException(RuntimeException::class);

        $bus->handle(new Envelope(new stdClass()));
    }

    /**
     * @param class-string $class
     */
    protected function dispatch(string $class, string $config = 'test-unique'): Dispatch
    {
        return new Dispatch($class, 'execute', [], null, 'default', $config);
    }

    protected function cacheKey(string $configName): string
    {
        $cacheKey = QueueManager::getConfig($configName)['uniqueCacheKey'] ?? null;
        $this->assertIsString($cacheKey);

        return $cacheKey;
    }
}
