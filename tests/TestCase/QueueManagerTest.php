<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @since 0.1.0
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Queue\Test\TestCase;

use BadMethodCallException;
use Cake\Log\Log;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Enqueue\SimpleClient\SimpleClient;
use LogicException;
use TestApp\Job\LogToDebugJob;

/**
 * QueueManager test
 */
class QueueManagerTest extends TestCase
{
    private $fsQueueUrl = 'file:///' . TMP . DS . 'queue';

    public function tearDown(): void
    {
        parent::tearDown();

        QueueManager::drop('test');
        Log::drop('test');

        // delete file based queues
        array_map('unlink', glob($this->fsQueueUrl . DS . '*'));
    }

    public function testSetConfig()
    {
        $result = QueueManager::setConfig('test', [
            'url' => 'null:',
        ]);
        $this->assertNull($result);

        $config = QueueManager::getConfig('test');
        $this->assertSame('null:', $config['url']);
    }

    public function testSetConfigInvalidValue()
    {
        $this->expectException(LogicException::class);
        QueueManager::setConfig('test', null);
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
            'url' => $this->fsQueueUrl,
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
                'transport' => 'file:'.TMP . 'enqueue-fs-test.tmp',
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
            'url' => $this->fsQueueUrl,
            'queue' => 'test',
        ]);

        QueueManager::push(LogToDebugJob::class, [], ['config' => 'test', 'queue' => 'non-default-queue-name']);

        $fsQueueFile = $this->fsQueueUrl . DS . 'enqueue.app.test';
        $this->assertFileExists($fsQueueFile);
        $this->assertStringContainsString('non-default-queue-name', file_get_contents($fsQueueFile));
    }
}
