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
use TestApp\Dto\OrderDto;
use TestApp\Dto\OrderItemDto;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\UniqueJob;
use TypeError;

/**
 * QueueManager test
 */
class QueueManagerTest extends TestCase
{
    use QueueTestTrait;

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

    public function testGetUniqueId()
    {
        $first = QueueManager::getUniqueId('Example', 'hello', [1, 2, 3]);
        $second = QueueManager::getUniqueId('Example', 'hello', [3, 2, 1]);
        $this->assertNotEquals($first, $second, 'values are sorted by key');

        $second = QueueManager::getUniqueId('Human', 'hello', [3, 2, 1]);
        $this->assertNotEquals($first, $second, 'class changes hash');

        $second = QueueManager::getUniqueId('Example', 'bye', [3, 2, 1]);
        $this->assertNotEquals($first, $second, 'method changes hash');

        $first = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'guest']);
        $second = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'guest']);
        $this->assertSame($first, $second, 'same values and keys are the same');

        $second = QueueManager::getUniqueId('Example', 'hello', ['role' => 'guest', 'user_id' => 'admin']);
        $this->assertSame($first, $second, 'reordered keys are the same');

        $first = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'guest']);
        $second = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'admin']);
        $this->assertNotEquals($first, $second, 'different values are distinct');

        $first = QueueManager::getUniqueId('Example', 'hello', ['foo' => 'admin', 'bar' => 'guest']);
        $second = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'guest']);
        $this->assertNotEquals($first, $second, 'different keys, same values are distinct');

        $first = QueueManager::getUniqueId('Example', 'hello', ['foo' => 'foo', 'bar' => 'foo']);
        $second = QueueManager::getUniqueId('Example', 'hello', ['user_id' => 'admin', 'role' => 'guest']);
        $this->assertNotEquals($first, $second, 'different keys and values, are distinct');

        $first = QueueManager::getUniqueId('Example', 'hello', ['arr' => ['a' => 1, 'b' => 2]]);
        $second = QueueManager::getUniqueId('Example', 'hello', ['arr' => ['b' => 2, 'a' => 1]]);
        $this->assertEquals($first, $second, 'nested arrays are sorted too');
    }

    /**
     * Test that the dtoClass argument is factored into the unique hash so two
     * different DTO types that happen to serialize identically don't collide.
     *
     * @return void
     */
    public function testGetUniqueIdWithDtoClass()
    {
        $data = ['id' => 7, 'customer' => 'Acme Corp'];

        $withoutDto = QueueManager::getUniqueId('Example', 'hello', $data);
        $withNullDto = QueueManager::getUniqueId('Example', 'hello', $data);
        $this->assertSame($withoutDto, $withNullDto, 'omitting dtoClass matches an explicit null');

        $withOrderDto = QueueManager::getUniqueId('Example', 'hello', $data, 'App\Dto\OrderDto');
        $this->assertNotEquals($withoutDto, $withOrderDto, 'a dtoClass changes the hash');

        $withOtherDto = QueueManager::getUniqueId('Example', 'hello', $data, 'App\Dto\OtherDto');
        $this->assertNotEquals($withOrderDto, $withOtherDto, 'different dtoClasses with identical data are distinct');

        $withOrderDtoAgain = QueueManager::getUniqueId('Example', 'hello', $data, 'App\Dto\OrderDto');
        $this->assertSame($withOrderDto, $withOrderDtoAgain, 'same dtoClass and data are the same');
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
        QueueManager::setConfig('test');
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

    public function testPushWithDtoObject()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
        ]);

        $dto = new OrderDto(7, 'Acme Corp', [
            new OrderItemDto('SKU-1', 2),
        ]);
        QueueManager::push(LogToDebugJob::class, $dto, ['config' => 'test']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $contents = file_get_contents($fsQueueFile);
        $this->assertStringContainsString('dtoClass', $contents);
        $this->assertStringContainsString('OrderDto', $contents);
        $this->assertStringContainsString('Acme Corp', $contents);
        $this->assertStringContainsString('SKU-1', $contents);
    }

    public function testPushWithDtoClassOption()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
        ]);

        QueueManager::push(LogToDebugJob::class, [
            'id' => 7,
            'customer' => 'Acme Corp',
        ], ['config' => 'test', 'dtoClass' => OrderDto::class]);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $contents = file_get_contents($fsQueueFile);
        $this->assertStringContainsString('dtoClass', $contents);
        $this->assertStringContainsString('OrderDto', $contents);
    }

    public function testPushWithoutDtoDoesNotAddDtoClass()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
        ]);

        QueueManager::push(LogToDebugJob::class, ['id' => 7], ['config' => 'test']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $contents = file_get_contents($fsQueueFile);
        $this->assertStringNotContainsString('dtoClass', $contents);
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

    /**
     * Test that pushing the same DTO twice for a unique job only queues it once.
     *
     * @return void
     */
    public function testUniqueMessageWithDtoObjectIsQueuedOnlyOnce()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
            'uniqueCache' => [
                'engine' => 'File',
            ],
        ]);

        $first = new OrderDto(7, 'Acme Corp', [
            new OrderItemDto('SKU-1', 2),
        ]);
        $second = new OrderDto(7, 'Acme Corp', [
            new OrderItemDto('SKU-1', 2),
        ]);

        QueueManager::push(UniqueJob::class, $first, ['config' => 'test']);
        QueueManager::push(UniqueJob::class, $second, ['config' => 'test']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $this->assertSame(1, substr_count(file_get_contents($fsQueueFile), 'UniqueJob'));
    }

    /**
     * Test that pushing DTOs with different field values for a unique job queues both.
     *
     * @return void
     */
    public function testUniqueMessageWithDifferentDtoObjectsAreBothQueued()
    {
        QueueManager::setConfig('test', [
            'url' => $this->getFsQueueUrl(),
            'queue' => 'test',
            'uniqueCache' => [
                'engine' => 'File',
            ],
        ]);

        $first = new OrderDto(7, 'Acme Corp', []);
        $second = new OrderDto(8, 'Other Corp', []);

        QueueManager::push(UniqueJob::class, $first, ['config' => 'test']);
        QueueManager::push(UniqueJob::class, $second, ['config' => 'test']);

        $fsQueueFile = $this->getFsQueueUrl() . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $this->assertSame(2, substr_count(file_get_contents($fsQueueFile), 'UniqueJob'));
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
