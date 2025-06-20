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
namespace Cake\Queue\Test\TestCase\Enqueue;

use Cake\Queue\Enqueue\SimpleClient;
use DateTime;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\LimitConsumedMessagesExtension;
use Enqueue\Consumption\Extension\LimitConsumptionTimeExtension;
use Enqueue\Consumption\Result;
use Interop\Queue\Exception\PurgeQueueNotSupportedException;
use Interop\Queue\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SimpleClientTest extends TestCase
{
    public static function transportConfigDataProvider()
    {
        yield 'amqp_dsn' => [[
            'transport' => getenv('AMQP_DSN'),
        ], '+1sec'];

        yield 'dbal_dsn' => [[
            'transport' => getenv('DOCTRINE_DSN'),
        ], '+1sec'];

        yield 'rabbitmq_stomp' => [[
            'transport' => [
                'dsn' => getenv('RABITMQ_STOMP_DSN'),
                'lazy' => false,
                'management_plugin_installed' => true,
            ],
        ], '+1sec'];

        yield 'predis_dsn' => [[
            'transport' => [
                'dsn' => getenv('PREDIS_DSN'),
                'lazy' => false,
            ],
        ], '+1sec'];

        yield 'fs_dsn' => [[
            'transport' => 'file://' . sys_get_temp_dir(),
        ], '+1sec'];

        yield 'sqs' => [[
            'transport' => [
                'dsn' => getenv('SQS_DSN'),
            ],
        ], '+1sec'];

        yield 'mongodb_dsn' => [[
            'transport' => getenv('MONGO_DSN'),
        ], '+1sec'];
    }

    public function testShouldWorkWithStringDsnConstructorArgument()
    {
        $actualMessage = null;

        $client = new SimpleClient(getenv('AMQP_DSN'));

        $client->bindTopic('foo_topic', function (Message $message) use (&$actualMessage) {
            $actualMessage = $message;

            return Result::ACK;
        });

        $client->setupBroker();
        $this->purgeQueue($client);

        $client->sendEvent('foo_topic', 'Hello there!');

        $client->getQueueConsumer()->setReceiveTimeout(200);
        $client->consume(new ChainExtension([
            new LimitConsumptionTimeExtension(new DateTime('+1sec')),
            new LimitConsumedMessagesExtension(2),
        ]));

        $this->assertInstanceOf(Message::class, $actualMessage);
        $this->assertSame('Hello there!', $actualMessage->getBody());
    }

    #[DataProvider('transportConfigDataProvider')]
    public function testSendEventWithOneSubscriber($config, string $timeLimit)
    {
        $actualMessage = null;

        $config['client'] = [
            'prefix' => str_replace('.', '', uniqid('enqueue', true)),
            'app_name' => 'simple_client',
            'router_topic' => 'test',
            'router_queue' => 'test',
            'default_queue' => 'test',
        ];

        $client = new SimpleClient($config);

        $client->bindTopic('foo_topic', function (Message $message) use (&$actualMessage) {
            $actualMessage = $message;

            return Result::ACK;
        });

        $client->setupBroker();
        $this->purgeQueue($client);

        $client->sendEvent('foo_topic', 'Hello there!');

        $client->getQueueConsumer()->setReceiveTimeout(200);
        $client->consume(new ChainExtension([
            new LimitConsumptionTimeExtension(new DateTime($timeLimit)),
            new LimitConsumedMessagesExtension(2),
        ]));

        $this->assertInstanceOf(Message::class, $actualMessage);
        $this->assertSame('Hello there!', $actualMessage->getBody());
    }

    #[DataProvider('transportConfigDataProvider')]
    public function testSendEventWithTwoSubscriber($config, string $timeLimit)
    {
        $received = 0;

        $config['client'] = [
            'prefix' => str_replace('.', '', uniqid('enqueue', true)),
            'app_name' => 'simple_client',
            'router_topic' => 'test',
            'router_queue' => 'test',
            'default_queue' => 'test',
        ];

        $client = new SimpleClient($config);

        $client->bindTopic('foo_topic', function () use (&$received) {
            ++$received;

            return Result::ACK;
        });
        $client->bindTopic('foo_topic', function () use (&$received) {
            ++$received;

            return Result::ACK;
        });

        $client->setupBroker();
        $this->purgeQueue($client);

        $client->sendEvent('foo_topic', 'Hello there!');
        $client->getQueueConsumer()->setReceiveTimeout(200);
        $client->consume(new ChainExtension([
            new LimitConsumptionTimeExtension(new DateTime($timeLimit)),
            new LimitConsumedMessagesExtension(3),
        ]));

        $this->assertSame(2, $received);
    }

    #[DataProvider('transportConfigDataProvider')]
    public function testSendCommand($config, string $timeLimit)
    {
        $received = 0;

        $config['client'] = [
            'prefix' => str_replace('.', '', uniqid('enqueue', true)),
            'app_name' => 'simple_client',
            'router_topic' => 'test',
            'router_queue' => 'test',
            'default_queue' => 'test',
        ];

        $client = new SimpleClient($config);

        $client->bindCommand('foo_command', function () use (&$received) {
            ++$received;

            return Result::ACK;
        });

        $client->setupBroker();
        $this->purgeQueue($client);

        $client->sendCommand('foo_command', 'Hello there!');
        $client->getQueueConsumer()->setReceiveTimeout(200);
        $client->consume(new ChainExtension([
            new LimitConsumptionTimeExtension(new DateTime($timeLimit)),
            new LimitConsumedMessagesExtension(1),
        ]));

        $this->assertSame(1, $received);
    }

    protected function purgeQueue(SimpleClient $client): void
    {
        $driver = $client->getDriver();

        $queue = $driver->createQueue($driver->getConfig()->getDefaultQueue());

        try {
            $client->getDriver()->getContext()->purgeQueue($queue);
        } catch (PurgeQueueNotSupportedException $e) {
        }
    }
}
