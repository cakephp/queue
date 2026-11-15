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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 4.0.0
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Queue\Test\TestCase\Attribute;

use Cake\Cache\Cache;
use Cake\Queue\Attribute\Config;
use Cake\Queue\Attribute\Delay;
use Cake\Queue\Attribute\Expires;
use Cake\Queue\Attribute\JobAttributeReader;
use Cake\Queue\Attribute\MaxAttempts;
use Cake\Queue\Attribute\Priority;
use Cake\Queue\Attribute\Queue;
use Cake\Queue\Attribute\ShouldBeUnique;
use Cake\Queue\Job\Message;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use TestApp\Job\AttributeChildJob;
use TestApp\Job\AttributeFullJob;
use TestApp\Job\AttributeOptOutUniqueJob;
use TestApp\Job\AttributeParentJob;
use TestApp\Job\AttributeQueueJob;
use TestApp\Job\AttributeStaticMixJob;
use TestApp\Job\AttributeUniqueJob;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\MaxAttemptsIsThreeJob;
use TestApp\Job\UniqueJob;
use Throwable;

class JobAttributeTest extends TestCase
{
    use QueueTestTrait;

    private string $fsQueuePath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fsQueuePath = TMP . DS . 'queue-attr';
        if (!is_dir($this->fsQueuePath)) {
            mkdir($this->fsQueuePath, 0777, true);
        }

        foreach (glob($this->fsQueuePath . DS . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->clearUniqueCache();
        JobAttributeReader::clearCache();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fsQueuePath . DS . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->clearUniqueCache();
        JobAttributeReader::clearCache();
        parent::tearDown();
    }

    private function clearUniqueCache(): void
    {
        $keys = ['Cake/Queue.queueUnique.test', 'Cake/Queue.queueUnique.other'];
        foreach (['test', 'other'] as $name) {
            $config = QueueManager::getConfig($name);
            $cacheKey = is_array($config) ? ($config['uniqueCacheKey'] ?? null) : null;
            if ($cacheKey && !in_array($cacheKey, $keys, true)) {
                $keys[] = $cacheKey;
            }
        }

        foreach ($keys as $cacheKey) {
            try {
                Cache::clear($cacheKey);
            } catch (Throwable) {
            }
        }
    }

    private function fsUrl(): string
    {
        return 'file:///' . $this->fsQueuePath;
    }

    private function queueFileContents(string $suffix = 'enqueue.app.test'): string
    {
        $file = $this->fsQueuePath . DS . $suffix;

        $this->assertFileExists($file);

        return (string)file_get_contents($file);
    }

    public function testReaderReturnsNullWhenNoAttribute(): void
    {
        $this->assertNull(JobAttributeReader::get(LogToDebugJob::class, Queue::class));
        $this->assertNull(JobAttributeReader::getQueue(LogToDebugJob::class));
        $this->assertNull(JobAttributeReader::getConfig(LogToDebugJob::class));
        $this->assertNull(JobAttributeReader::getDelay(LogToDebugJob::class));
        $this->assertNull(JobAttributeReader::getExpires(LogToDebugJob::class));
        $this->assertNull(JobAttributeReader::getPriority(LogToDebugJob::class));
    }

    public function testReaderResolvesFullJob(): void
    {
        $this->assertSame('attribute-queue', JobAttributeReader::getQueue(AttributeFullJob::class));
        $this->assertSame('test', JobAttributeReader::getConfig(AttributeFullJob::class));
        $this->assertSame(90, JobAttributeReader::getDelay(AttributeFullJob::class));
        $this->assertSame(3600, JobAttributeReader::getExpires(AttributeFullJob::class));
        $this->assertSame('high', JobAttributeReader::getPriority(AttributeFullJob::class));
        $this->assertSame(7, JobAttributeReader::getMaxAttempts(AttributeFullJob::class));
        $this->assertTrue(JobAttributeReader::isUnique(AttributeFullJob::class));

        $queue = JobAttributeReader::get(AttributeFullJob::class, Queue::class);
        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertInstanceOf(Config::class, JobAttributeReader::get(AttributeFullJob::class, Config::class));
        $this->assertInstanceOf(Delay::class, JobAttributeReader::get(AttributeFullJob::class, Delay::class));
        $this->assertInstanceOf(Expires::class, JobAttributeReader::get(AttributeFullJob::class, Expires::class));
        $this->assertInstanceOf(Priority::class, JobAttributeReader::get(AttributeFullJob::class, Priority::class));
        $this->assertInstanceOf(MaxAttempts::class, JobAttributeReader::get(AttributeFullJob::class, MaxAttempts::class));
        $this->assertInstanceOf(ShouldBeUnique::class, JobAttributeReader::get(AttributeFullJob::class, ShouldBeUnique::class));
    }

    public function testAttributeWinsOverStaticProperty(): void
    {
        $this->assertSame(3, MaxAttemptsIsThreeJob::$maxAttempts);
        $this->assertSame(9, JobAttributeReader::getMaxAttempts(AttributeStaticMixJob::class));
    }

    public function testStaticPropertyUsedWhenNoAttribute(): void
    {
        $this->assertSame(3, JobAttributeReader::getMaxAttempts(MaxAttemptsIsThreeJob::class));
        $this->assertNull(JobAttributeReader::getMaxAttempts(LogToDebugJob::class));
    }

    public function testIsUniqueFallsBackToStatic(): void
    {
        $this->assertTrue(JobAttributeReader::isUnique(UniqueJob::class));
        $this->assertTrue(JobAttributeReader::isUnique(AttributeUniqueJob::class));
        $this->assertFalse(JobAttributeReader::isUnique(LogToDebugJob::class));
    }

    public function testAttributeCanOptOutOfStaticUnique(): void
    {
        $this->assertTrue((bool)AttributeOptOutUniqueJob::$shouldBeUnique);
        $this->assertFalse(JobAttributeReader::isUnique(AttributeOptOutUniqueJob::class));
    }

    public function testAttributeInheritedFromParent(): void
    {
        $this->assertSame('parent-queue', JobAttributeReader::getQueue(AttributeParentJob::class));
        $this->assertSame('parent-queue', JobAttributeReader::getQueue(AttributeChildJob::class));
    }

    public function testMessageGetMaxAttemptsUsesAttribute(): void
    {
        $message = $this->messageFor(AttributeFullJob::class);
        $this->assertSame(7, $message->getMaxAttempts());
    }

    public function testMessageGetMaxAttemptsPrefersAttributeOverStatic(): void
    {
        $message = $this->messageFor(AttributeStaticMixJob::class);
        $this->assertSame(9, $message->getMaxAttempts());
    }

    public function testMessageGetMaxAttemptsFallsBackToStatic(): void
    {
        $message = $this->messageFor(MaxAttemptsIsThreeJob::class);
        $this->assertSame(3, $message->getMaxAttempts());
    }

    public function testMessageGetMaxAttemptsNullWhenNothingDeclared(): void
    {
        $message = $this->messageFor(LogToDebugJob::class);
        $this->assertNull($message->getMaxAttempts());
    }

    public function testPushUsesQueueAttributeWhenOptionMissing(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
        ]);

        QueueManager::push(AttributeQueueJob::class, ['id' => 1], ['config' => 'test']);

        $contents = $this->queueFileContents();
        $this->assertStringContainsString('attribute-queue', $contents);
    }

    public function testPushExplicitQueueOptionWinsOverAttribute(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
        ]);

        QueueManager::push(AttributeQueueJob::class, ['id' => 1], ['config' => 'test', 'queue' => 'explicit-queue']);

        $contents = $this->queueFileContents();
        $this->assertStringContainsString('explicit-queue', $contents);
        $this->assertStringNotContainsString('attribute-queue', $contents);
    }

    public function testPushUsesConfigAttributeWhenOptionMissing(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        QueueManager::push(AttributeFullJob::class, ['id' => 1]);

        $contents = $this->queueFileContents();
        $this->assertStringContainsString('AttributeFullJob', $contents);
    }

    public function testPushExplicitConfigOptionWinsOverAttribute(): void
    {
        $otherPath = TMP . DS . 'queue-attr-other';
        if (!is_dir($otherPath)) {
            mkdir($otherPath, 0777, true);
        }

        foreach (glob($otherPath . DS . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);
        QueueManager::setConfig('other', [
            'url' => 'file:///' . $otherPath,
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        try {
            QueueManager::push(AttributeFullJob::class, ['id' => 1], ['config' => 'other']);

            $otherFile = $otherPath . DS . 'enqueue.app.test';
            $this->assertFileExists($otherFile);
            $this->assertStringContainsString('AttributeFullJob', (string)file_get_contents($otherFile));
        } finally {
            foreach (glob($otherPath . DS . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($otherPath);
        }
    }

    public function testPushAppliesDelayExpiresPriorityAttributes(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        QueueManager::push(AttributeFullJob::class, ['id' => 1], ['config' => 'test']);

        $contents = $this->queueFileContents();
        $this->assertStringContainsString('enqueue.delay', $contents);
        $this->assertStringContainsString('90', $contents);
        $this->assertStringContainsString('enqueue.expire', $contents);
        $this->assertStringContainsString('3600', $contents);
        $this->assertStringContainsString('enqueue.priority', $contents);
        $this->assertStringContainsString('high', $contents);
    }

    public function testPushExplicitDelayWinsOverAttribute(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        QueueManager::push(AttributeFullJob::class, ['id' => 1], ['config' => 'test', 'delay' => 5]);

        $contents = $this->queueFileContents();
        $this->assertStringContainsString('"enqueue.delay":5', $contents);
        $this->assertStringNotContainsString('"enqueue.delay":90', $contents);
    }

    public function testUniqueAttributeQueuesOnlyOnce(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        QueueManager::push(AttributeUniqueJob::class, ['id' => 1], ['config' => 'test']);
        QueueManager::push(AttributeUniqueJob::class, ['id' => 1], ['config' => 'test']);

        $contents = $this->queueFileContents();
        $this->assertSame(1, substr_count($contents, 'AttributeUniqueJob'));
    }

    public function testOptOutAttributeDisablesStaticUnique(): void
    {
        QueueManager::setConfig('test', [
            'url' => $this->fsUrl(),
            'queue' => 'test',
            'uniqueCache' => ['engine' => 'File'],
        ]);

        QueueManager::push(AttributeOptOutUniqueJob::class, ['id' => 1], ['config' => 'test']);
        QueueManager::push(AttributeOptOutUniqueJob::class, ['id' => 1], ['config' => 'test']);

        $contents = $this->queueFileContents();
        $this->assertSame(2, substr_count($contents, 'AttributeOptOutUniqueJob'));
    }

    /**
     * @param class-string $class
     */
    private function messageFor(string $class): Message
    {
        $factory = new NullConnectionFactory();
        $context = $factory->createContext();

        return new Message(
            new NullMessage((string)json_encode([
                'class' => [$class, 'execute'],
                'data' => ['id' => 1],
            ])),
            $context,
        );
    }
}
