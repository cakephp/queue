<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 0.1.0
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Queue\Test\TestCase;

use BadMethodCallException;
use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Enqueue\SimpleClient\SimpleClient;
use LogicException;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\UniqueJob;
use TypeError;

/**
 * QueueManager test
 */
class QueueManagerTest extends TestCase
{
    use DebugLogTrait;

    private $fsQueuePath = TMP . DS . 'queue';

    private function getFsQueueUrl(): string
    {
        return 'file:///' . $this->fsQueuePath;
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $cacheKey = QueueManager::getConfig('test')['uniqueCacheKey'] ?? null;
        if ($cacheKey) {
            Cache::clear($cacheKey);
            Cache::drop($cacheKey);
        }

        QueueManager::drop('test');
        Log::drop('test');

        // delete file based queues
        array_map('unlink', glob($this->fsQueuePath . DS . '*'));
    }

    public function testSetConfig()
    {
        QueueManager::setConfig('test', [
            'url' => 'null:',
        ]);

        $config = QueueManager::getConfig('test');
        $this->assertSame('null:', $config['url']);
    }

    public function testSetMultipleConfigs()
    {
        QueueManager::setConfig('test', [
            'url' => 'null:',
            'uniqueCache' => [
                'engine' => 'File',
            ],
            'logger' => 'debug',
        ]);

        QueueManager::setConfig('other', [
            'url' => 'null:',
            'uniqueCache' => [
                'engine' => 'File',
            ],
            'logger' => 'debug',
        ]);

        $testConfig = QueueManager::getConfig('test');
        $this->assertSame('null:', $testConfig['url']);

        $otherConfig = QueueManager::getConfig('other');
        $this->assertSame('null:', $otherConfig['url']);

        QueueManager::drop('other');
    }

    public function testSetConfigWithInvalidConfigValue()
    {
        $this->expectException(LogicException::class);
        QueueManager::setConfig('test', null);
    }

    public function testSetConfigInvalidKeyValue()
    {
        $this->expectException(TypeError::class);
        QueueManager::setConfig(['test' => []], 'default');
    }

    public function testSetConfigNoUrl()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Must specify `url`');
        QueueManager::setConfig('test', ['queue' => 'test']);
    }

    public function testSetConfigOverwrite()
    {
        QueueManager::setConfig('test', [
            'url' => 'null:',
        ]);
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot reconfigure');
        QueueManager::setConfig('test', [
            'url' => 'redis:',
        ]);
    }

    public function testNonDefaultQueueNameString()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'other',
        ]);
        $engine = QueueManager::engine('test');
        $this->assertInstanceOf(SimpleClient::class, $engine);
        $this->assertSame('other', $engine->getDriver()->getConfig()->getRouterQueue());
    }

    public function testNonDefaultQueueNameArray()
    {
        QueueManager::setConfig('test', [
            'url' => [
                'transport' => 'file:' . TMP . 'fs-test.tmp',
                'client' => [
                    'router_queue' => 'other',
                ],
            ],
            'queue' => 'ignored',
        ]);
        $engine = QueueManager::engine('test');
        $this->assertInstanceOf(SimpleClient::class, $engine);
        $this->assertSame('other', $engine->getDriver()->getConfig()->getRouterQueue());
    }

    public function testEngine()
    {
        QueueManager::setConfig('test', [
            'queue' => 'default',
            'url' => 'null:',
        ]);
        $engine = QueueManager::engine('test');
        $this->assertInstanceOf(SimpleClient::class, $engine);

        $this->assertSame($engine, QueueManager::engine('test'));
    }

    public function testPushInvalidClass()
    {
        $this->expectException('\InvalidArgumentException');
        $this->expectExceptionMessage('class does not exist.');
        QueueManager::push('NotARealJob');
    }

    public function testMessageIsPushedToQueuePassedAsOption()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
        ]);

        QueueManager::push(LogToDebugJob::class, [], ['config' => 'test', 'queue' => 'non-default-queue-name']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $this->assertStringContainsString('non-default-queue-name', file_get_contents($fsQueueFile));
    }

    public function testUniqueMessageIsQueuedOnlyOnce()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
            'uniqueCache' => [
                'engine' => 'File',
            ],
        ]);

        QueueManager::push(UniqueJob::class, [], ['config' => 'test']);
        QueueManager::push(UniqueJob::class, [], ['config' => 'test']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $this->assertSame(1, substr_count(file_get_contents($fsQueueFile), 'UniqueJob'));
    }

    public function testDroppedJobIsLoggedForUniqueJob()
    {
        Log::setConfig('debug', [
            'className' => 'Array',
            'levels' => ['notice', 'info', 'debug'],
        ]);

        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
            'uniqueCache' => [
                'engine' => 'File',
            ],
            'logger' => 'debug',
        ]);

        QueueManager::push(UniqueJob::class, [], ['config' => 'test']);
        QueueManager::push(UniqueJob::class, [], ['config' => 'test']);

        $this->assertDebugLogContainsExactly('An identical instance of TestApp\Job\UniqueJob already exists on the queue. This push will be ignored.', 1);

        Log::drop('debug');
    }
}
