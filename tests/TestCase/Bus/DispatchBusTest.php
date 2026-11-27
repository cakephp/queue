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

use Cake\Cache\Cache;
use Cake\Queue\Bus\BusFactory;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\DispatchResult;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\Queue\TestSuite\QueueTrait;
use Cake\Queue\TestSuite\TestQueueClient;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use TestApp\Bus\CallbackMiddleware;
use TestApp\Bus\TenantStamp;
use TestApp\Bus\TenantTagMiddleware;
use TestApp\Bus\TestMessage;
use TestApp\Dto\OrderDto;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\UniqueJob;

class DispatchBusTest extends TestCase
{
    use QueueTestTrait;
    use QueueTrait;

    private string $fsQueuePath = TMP . DS . 'queue-bus';

    public function tearDown(): void
    {
        parent::tearDown();
        array_map('unlink', glob($this->fsQueuePath . DS . '*') ?: []);
    }

    public function testStampPassthrough(): void
    {
        QueueManager::push(
            LogToDebugJob::class,
            ['id' => 1],
            ['stamps' => [new TenantStamp('tenant:acme')]],
        );

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertCount(1, $jobs);
        $metadata = $jobs[0]['metadata'];
        $this->assertSame(
            [['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme']],
            $metadata['stamps'],
        );
        $this->assertArrayNotHasKey('tags', $metadata);
        $this->assertSame(['id' => 1], $jobs[0]['data']);
    }

    public function testLegacyMetadataPassesThroughVerbatim(): void
    {
        QueueManager::push(
            LogToDebugJob::class,
            ['id' => 1],
            ['metadata' => ['tags' => ['finance'], '_uniqueId' => 'abc123', 'custom' => 'kept']],
        );

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $metadata = $jobs[0]['metadata'];
        $this->assertSame(
            ['tags' => ['finance'], '_uniqueId' => 'abc123', 'custom' => 'kept'],
            $metadata,
        );
    }

    public function testUserMiddlewareStampVisibleToSend(): void
    {
        QueueManager::setConfig('test-ordered', [
            'url' => 'test:',
            BusFactory::DISPATCH_MIDDLEWARE => [TenantTagMiddleware::class],
        ]);

        QueueManager::push(LogToDebugJob::class, ['id' => 1], ['config' => 'test-ordered']);

        $jobs = TestQueueClient::getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertCount(1, $jobs);
        $this->assertSame(
            [['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:test']],
            $jobs[0]['metadata']['stamps'] ?? null,
        );
    }

    public function testUserMiddlewareCanRerouteWithWithers(): void
    {
        QueueManager::setConfig('test-reroute', [
            'url' => 'test:',
            'queue' => 'default',
            BusFactory::DISPATCH_MIDDLEWARE => [
                new CallbackMiddleware(
                    static function (Envelope $envelope, BusStackInterface $stack): Envelope {
                        $dispatch = $envelope->getMessage();
                        if (!$dispatch instanceof Dispatch) {
                            throw new RuntimeException('Expected a Dispatch message.');
                        }

                        return $stack->next()->handle(
                            $envelope->withMessage($dispatch->withQueue('rerouted')),
                            $stack,
                        );
                    },
                ),
            ],
        ]);

        QueueManager::push(LogToDebugJob::class, ['id' => 1], ['config' => 'test-reroute']);

        $this->assertCount(1, TestQueueClient::getQueuedJobsByQueue('rerouted'));
        $this->assertCount(1, TestQueueClient::getQueuedJobs());
    }

    public function testPushWithoutStampsOrMetadataOmitsMetadata(): void
    {
        QueueManager::push(LogToDebugJob::class, ['id' => 1]);

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertSame([], $jobs[0]['metadata']);
    }

    public function testDuplicatePushReturnsSkippedResult(): void
    {
        QueueManager::setConfig('test-fs', [
            'url' => 'file:///' . $this->fsQueuePath,
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);
        $this->clearUniqueCache('test-fs');

        $first = QueueManager::push(UniqueJob::class, [], ['config' => 'test-fs']);
        $second = QueueManager::push(UniqueJob::class, [], ['config' => 'test-fs']);

        $this->assertTrue($first->isSent());
        $this->assertIsString($first->messageId);
        $this->assertNotEmpty($first->messageId);
        $this->assertTrue($second->isSkipped());
        $this->assertNull($second->messageId);
    }

    public function testDispatchResultFromEnvelope(): void
    {
        $sent = DispatchResult::fromEnvelope(new Envelope(new TestMessage('hello'), [new SentStamp('abc')]));
        $this->assertTrue($sent->isSent());
        $this->assertFalse($sent->isSkipped());
        $this->assertSame('abc', $sent->messageId);

        $sentWithoutId = DispatchResult::fromEnvelope(new Envelope(new TestMessage('hello'), [new SentStamp()]));
        $this->assertTrue($sentWithoutId->isSent());
        $this->assertFalse($sentWithoutId->isSkipped());
        $this->assertNull($sentWithoutId->messageId);

        $skipped = DispatchResult::fromEnvelope(new Envelope(new TestMessage('hello'), [new SkippedAsDuplicateStamp()]));
        $this->assertTrue($skipped->isSkipped());
        $this->assertFalse($skipped->isSent());
        $this->assertNull($skipped->messageId);

        // Synthetic-only case: the real pipeline short-circuits duplicates
        // before SendMiddleware, so both stamps can only coexist via user
        // middleware. SentStamp wins because the message was actually sent.
        $both = DispatchResult::fromEnvelope(new Envelope(new TestMessage('hello'), [
            new SkippedAsDuplicateStamp(),
            new SentStamp('abc'),
        ]));
        $this->assertTrue($both->isSent());
        $this->assertSame('abc', $both->messageId);

        $this->expectException(LogicException::class);
        DispatchResult::fromEnvelope(new Envelope(new TestMessage('hello')));
    }

    public function testPushReturnsMessageId(): void
    {
        QueueManager::setConfig('test-fs', [
            'url' => 'file:///' . $this->fsQueuePath,
            'queue' => 'test',
        ]);

        $result = QueueManager::push(LogToDebugJob::class, ['id' => 1], ['config' => 'test-fs']);

        $this->assertTrue($result->isSent());
        $this->assertIsString($result->messageId);
        $this->assertNotEmpty($result->messageId);
    }

    public function testPushRejectsNonArrayStampsAndMetadata(): void
    {
        QueueManager::setConfig('test', ['url' => 'null:']);

        try {
            QueueManager::push(LogToDebugJob::class, [], ['config' => 'test', 'stamps' => 'nope']);
            $this->fail('Non-array stamps must throw.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->assertStringContainsString('stamps', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata');

        QueueManager::push(LogToDebugJob::class, [], ['config' => 'test', 'metadata' => 'nope']);
    }

    public function testPushRecordsStampsAndDtoClassTogether(): void
    {
        QueueManager::setConfig('test', [
            'url' => 'file:///' . $this->fsQueuePath,
            'queue' => 'test',
        ]);

        QueueManager::push(LogToDebugJob::class, ['id' => 7], [
            'config' => 'test',
            'dtoClass' => OrderDto::class,
            'stamps' => [new TenantStamp('tenant:acme')],
        ]);

        $file = $this->fsQueuePath . DS . 'enqueue.app.test';
        $this->assertFileExists($file);
        $contents = (string)file_get_contents($file);
        $this->assertStringContainsString('dtoClass', $contents);
        $this->assertStringContainsString('OrderDto', $contents);
        $this->assertStringContainsString('TenantStamp', $contents);
    }

    protected function clearUniqueCache(string $configName): void
    {
        $cacheKey = QueueManager::getConfig($configName)['uniqueCacheKey'] ?? null;
        if ($cacheKey !== null) {
            Cache::clear($cacheKey);
        }
    }
}
