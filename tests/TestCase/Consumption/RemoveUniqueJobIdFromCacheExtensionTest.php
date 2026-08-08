<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase\Job;

use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\Consumption\LimitConsumedMessagesExtension;
use Cake\Queue\Consumption\RemoveUniqueJobIdFromCacheExtension;
use Cake\Queue\Queue\Processor as QueueProcessor;
use Cake\Queue\QueueManager;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\ChainExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\BeforeClass;
use Psr\Log\NullLogger;
use TestApp\Dto\OrderDto;
use TestApp\Job\UniqueJob;

class RemoveUniqueJobIdFromCacheExtensionTest extends TestCase
{
    use QueueTestTrait;

    #[BeforeClass, After]
    public static function dropConfigs()
    {
        Log::drop('debug');

        $cacheKey = QueueManager::getConfig('default')['uniqueCacheKey'] ?? null;
        QueueManager::drop('default');

        if ($cacheKey) {
            Cache::clear($cacheKey);
            Cache::drop($cacheKey);
        }
    }

    public function testJobIsRemovedFromCacheAfterProcessing()
    {
        $consume = $this->setupQueue();

        QueueManager::push(UniqueJob::class, []);

        $uniqueId = QueueManager::getUniqueId(UniqueJob::class, 'execute', []);
        $this->assertTrue(Cache::read($uniqueId, 'Cake/Queue.queueUnique.default'));

        $consume();

        $this->assertNull(Cache::read($uniqueId, 'Cake/Queue.queueUnique.default'));
    }

    /**
     * Test that a unique job dispatched with a DTO is removed from the cache
     * using a hash that includes the dtoClass, matching the one computed at push time.
     *
     * @return void
     */
    public function testJobWithDtoIsRemovedFromCacheAfterProcessing()
    {
        $consume = $this->setupQueue();

        $dto = new OrderDto(7, 'Acme Corp', []);
        QueueManager::push(UniqueJob::class, $dto);

        $uniqueId = QueueManager::getUniqueId(
            UniqueJob::class,
            'execute',
            ['id' => 7, 'customer' => 'Acme Corp', 'items' => []],
            OrderDto::class,
        );
        $this->assertTrue(Cache::read($uniqueId, 'Cake/Queue.queueUnique.default'));

        $consume();

        $this->assertNull(Cache::read($uniqueId, 'Cake/Queue.queueUnique.default'));
    }

    protected function setupQueue()
    {
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['debug'],
        ]);

        QueueManager::setConfig('default', [
            'url' => 'file:///' . TMP . DS . uniqid('queue'),
            'receiveTimeout' => 100,
            'uniqueCache' => [
                'engine' => 'File',
            ],
        ]);

        $client = QueueManager::engine('default');

        $processor = new QueueProcessor(new NullLogger());
        $client->bindTopic('default', $processor);

        $extension = new ChainExtension([
            new LimitConsumedMessagesExtension(1),
            new RemoveUniqueJobIdFromCacheExtension('Cake/Queue.queueUnique.default'),
        ]);

        return function () use ($client, $extension) {
            $client->consume($extension);
        };
    }
}
