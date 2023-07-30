<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase\Job;

use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\Consumption\LimitConsumedMessagesExtension;
use Cake\Queue\Consumption\RemoveUniqueJobIdFromCacheExtension;
use Cake\Queue\Queue\Processor as QueueProcessor;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\ChainExtension;
use Psr\Log\NullLogger;
use TestApp\Job\UniqueJob;

class RemoveUniqueJobIdFromCacheExtensionTest extends TestCase
{
    /**
     * @beforeClass
     * @after
     */
    public static function dropConfigs()
    {
        Log::drop('debug');

        QueueManager::drop('default');

        $cacheKey = QueueManager::getConfig('default')['uniqueCacheKey'] ?? null;
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
