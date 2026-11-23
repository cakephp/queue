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
namespace Cake\Queue\Test\TestCase\Client;

use Cake\Event\EventInterface;
use Cake\Queue\Client\EnqueueClient;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\Message;
use Enqueue\Client\Producer;
use Enqueue\Client\ProducerInterface;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\LimitConsumedMessagesExtension;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullConnectionFactory;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use TestApp\Job\LogToDebugJob;
use TestApp\Queue\TestCustomClient;

class EnqueueClientTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        QueueManager::drop('test-produce');
        QueueManager::drop('test-custom-client');
        QueueManager::drop('test-bad-client');
        QueueManager::setContainer(null);
    }

    public function testBuildCreatesDriverAndProducer(): void
    {
        $client = new EnqueueClient('null:');
        $this->assertInstanceOf(DriverInterface::class, $client->getDriver());
        $this->assertInstanceOf(Producer::class, $client->getProducer());
        $this->assertNotEmpty($client->getDriver()->getConfig()->getRouterProcessor());
        $client->setupBroker();
        $this->assertTrue(true);
    }

    public function testBindTopicAndCommand(): void
    {
        $client = new EnqueueClient('null:');
        $client->bindTopic('demo-topic', function () {
            return 'ack';
        }, 'demo-topic-proc');
        $client->bindCommand('demo-command', function () {
            return 'ack';
        }, 'demo-command-proc');

        $routes = $client->getDriver()->getRouteCollection()->all();
        $this->assertNotEmpty($routes);
    }

    public function testSendEventEmitsSuccessWithMessageId(): void
    {
        $client = new EnqueueClient('null:');
        $captured = [];
        $client->getEventManager()->on('Queue.produce.success', function (EventInterface $event) use (&$captured) {
            $captured = $event->getData();
        });

        $client->sendEvent('demo', new Message(['a' => 1]));

        $this->assertNotEmpty($captured['messageId']);
        $this->assertSame('demo', $captured['topic']);
        $this->assertNotNull($captured['transportDestination']);
        $this->assertArrayNotHasKey('message', $captured);
        $this->assertArrayNotHasKey('transportMessage', $captured);
    }

    public function testSendEventAcceptsJsonSerializableBody(): void
    {
        $client = new EnqueueClient('null:');
        $captured = [];
        $client->getEventManager()->on('Queue.produce.success', function (EventInterface $event) use (&$captured) {
            $captured = $event->getData();
        });

        $client->sendEvent('demo', new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['a' => 1];
            }
        });

        $this->assertNotEmpty($captured['messageId']);
        $this->assertSame('demo', $captured['topic']);
    }

    public function testSendEventEmitsFailureAndRethrows(): void
    {
        $client = new EnqueueClient('null:');
        $failingProducer = $this->createMock(ProducerInterface::class);
        $failingProducer->expects($this->once())->method('sendEvent')->willThrowException(new RuntimeException('broker down'));

        $ref = new ReflectionProperty(EnqueueClient::class, 'producer');
        $ref->setValue($client, $failingProducer);

        $failures = [];
        $successes = [];
        $client->getEventManager()->on('Queue.produce.failure', function (EventInterface $event) use (&$failures) {
            $failures = $event->getData();
        });
        $client->getEventManager()->on('Queue.produce.success', function () use (&$successes) {
            $successes[] = true;
        });

        try {
            $client->sendEvent('demo', new Message('x'));
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('broker down', $runtimeException->getMessage());
        }

        $this->assertSame('demo', $failures['topic']);
        $this->assertInstanceOf(RuntimeException::class, $failures['exception']);
        $this->assertArrayNotHasKey('message', $failures);
        $this->assertSame([], $successes);
    }

    public function testSendCommandEmitsSuccess(): void
    {
        $client = new EnqueueClient('null:');
        $client->bindCommand('demo-cmd', function () {
            return 'ack';
        }, 'demo-cmd-proc');
        $captured = [];
        $client->getEventManager()->on('Queue.produce.success', function (EventInterface $event) use (&$captured) {
            $captured = $event->getData();
        });

        $client->sendCommand('demo-cmd', new Message(['a' => 1]));

        $this->assertNotEmpty($captured['messageId']);
        $this->assertSame('demo-cmd', $captured['command']);
    }

    public function testConnectionFactoryClassIsUsed(): void
    {
        $client = new EnqueueClient(['transport' => ['dsn' => 'null:', 'connection_factory_class' => NullConnectionFactory::class]]);
        $this->assertInstanceOf(DriverInterface::class, $client->getDriver());
    }

    public function testConnectionFactoryClassIsResolvedFromContainer(): void
    {
        $factory = new NullConnectionFactory();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->expects($this->once())->method('get')->with(NullConnectionFactory::class)->willReturn($factory);

        $client = new EnqueueClient(
            ['transport' => ['dsn' => 'null:', 'connection_factory_class' => NullConnectionFactory::class]],
            null,
            $container,
        );
        $this->assertInstanceOf(DriverInterface::class, $client->getDriver());
    }

    public function testConnectionFactoryServiceMustImplementConnectionFactory(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new stdClass());

        $this->expectException(LogicException::class);
        new EnqueueClient(
            ['transport' => ['dsn' => 'null:', 'connection_factory_class' => NullConnectionFactory::class]],
            null,
            $container,
        );
    }

    public function testConnectionFactoryClassIsValidatedBeforeInstantiation(): void
    {
        $this->expectException(LogicException::class);
        new EnqueueClient(['transport' => ['dsn' => 'null:', 'connection_factory_class' => 'NoSuch\\FactoryClass']]);
    }

    public function testConnectionFactoryClassMustBeNonEmptyString(): void
    {
        $this->expectException(LogicException::class);
        new EnqueueClient(['transport' => ['dsn' => 'null:', 'connection_factory_class' => 123]]);
    }

    public function testNestedSendKeepsOuterPostSendResult(): void
    {
        $client = new EnqueueClient('null:');
        $events = [];
        $client->getEventManager()->on('Queue.produce.success', function (EventInterface $event) use (&$events, $client) {
            $data = $event->getData();
            if ($data['topic'] === 'outer') {
                $client->sendEvent('inner', new Message(['nested' => true]));
            }

            $events[] = $data;
        });

        $client->sendEvent('outer', new Message(['outer' => true]));

        $this->assertCount(2, $events);
        $byTopic = [];
        foreach ($events as $data) {
            $byTopic[$data['topic']] = $data;
        }

        $this->assertNotEmpty($byTopic['outer']['messageId']);
        $this->assertNotEmpty($byTopic['inner']['messageId']);
        $this->assertNotSame($byTopic['inner']['messageId'], $byTopic['outer']['messageId']);
    }

    /**
     * String body roundtrip through one topic subscriber.
     *
     * @return void
     */
    public function testSendEventWithOneSubscriber(): void
    {
        $dir = $this->fsTransportDir();
        try {
            $client = $this->fsClient($dir);

            $actualMessage = null;
            $client->bindTopic('foo_topic', function ($message) use (&$actualMessage) {
                $actualMessage = $message;

                return Result::ACK;
            }, 'foo-topic-proc');

            $client->setupBroker();
            $client->sendEvent('foo_topic', 'Hello there!');

            $client->getQueueConsumer()->setReceiveTimeout(1000);
            $client->consume(new ChainExtension([
                new LimitConsumedMessagesExtension(2),
            ]));

            $this->assertNotNull($actualMessage);
            $this->assertSame('Hello there!', $actualMessage->getBody());
        } finally {
            $this->removeFsTransportDir($dir);
        }
    }

    /**
     * Two subscribers on the same topic both receive the event.
     *
     * @return void
     */
    public function testSendEventWithTwoSubscribers(): void
    {
        $dir = $this->fsTransportDir();
        try {
            $client = $this->fsClient($dir);

            $received = 0;
            $client->bindTopic('foo_topic', function () use (&$received) {
                ++$received;

                return Result::ACK;
            }, 'foo-topic-proc-one');
            $client->bindTopic('foo_topic', function () use (&$received) {
                ++$received;

                return Result::ACK;
            }, 'foo-topic-proc-two');

            $client->setupBroker();
            $client->sendEvent('foo_topic', 'Hello there!');

            $client->getQueueConsumer()->setReceiveTimeout(1000);
            $client->consume(new ChainExtension([
                new LimitConsumedMessagesExtension(3),
            ]));

            $this->assertSame(2, $received);
        } finally {
            $this->removeFsTransportDir($dir);
        }
    }

    /**
     * Command roundtrip to a bound command processor.
     *
     * @return void
     */
    public function testSendCommandRoundtrip(): void
    {
        $dir = $this->fsTransportDir();
        try {
            $client = $this->fsClient($dir);

            $received = 0;
            $client->bindCommand('foo_command', function () use (&$received) {
                ++$received;

                return Result::ACK;
            }, 'foo-command-proc');

            $client->setupBroker();
            $client->sendCommand('foo_command', 'Hello there!');

            $client->getQueueConsumer()->setReceiveTimeout(1000);
            $client->consume(new ChainExtension([
                new LimitConsumedMessagesExtension(1),
            ]));

            $this->assertSame(1, $received);
        } finally {
            $this->removeFsTransportDir($dir);
        }
    }

    /**
     * Create a client on the file transport with a unique prefix per test.
     *
     * Uses a partial client config on purpose: separator, extensions and
     * delay options must fall back to defaults.
     *
     * @param string $dir Transport directory.
     * @return \Cake\Queue\Client\EnqueueClient
     */
    protected function fsClient(string $dir): EnqueueClient
    {
        return new EnqueueClient([
            'transport' => 'file:///' . $dir,
            'client' => [
                'prefix' => str_replace('.', '', uniqid('enqueue', true)),
                'app_name' => 'enqueue_client',
                'router_topic' => 'test',
                'router_queue' => 'test',
                'default_queue' => 'test',
            ],
        ]);
    }

    /**
     * Create an isolated directory for the file transport.
     *
     * @return string Directory path with trailing separator.
     */
    protected function fsTransportDir(): string
    {
        $dir = TMP . 'enqueue-client-test-' . uniqid() . DS;
        mkdir($dir, 0777, true);

        return $dir;
    }

    /**
     * Remove an isolated file transport directory.
     *
     * @param string $dir Directory path with trailing separator.
     * @return void
     */
    protected function removeFsTransportDir(string $dir): void
    {
        array_map('unlink', glob($dir . '*'));
        rmdir($dir);
    }

    public function testFactoryServiceIsRejected(): void
    {
        $this->expectException(LogicException::class);
        new EnqueueClient(['transport' => ['dsn' => 'null:', 'factory_service' => 'some_service']]);
    }

    public function testQueueManagerPushReturnsMessageIdAndEmitsSuccess(): void
    {
        QueueManager::setConfig('test-produce', ['url' => 'null:', 'queue' => 'default']);
        $engine = QueueManager::engine('test-produce');

        $captured = [];
        $engine->getEventManager()->on('Queue.produce.success', function (EventInterface $event) use (&$captured) {
            $captured = $event->getData();
        });

        $messageId = QueueManager::push(LogToDebugJob::class, ['foo' => 'bar'], ['config' => 'test-produce']);

        $this->assertNotEmpty($messageId);
        $this->assertSame($messageId, $captured['messageId']);
    }

    public function testQueueManagerUsesCustomClientClass(): void
    {
        QueueManager::setConfig('test-custom-client', [
            'url' => 'null:',
            'queue' => 'default',
            'clientClass' => TestCustomClient::class,
        ]);
        $engine = QueueManager::engine('test-custom-client');
        $this->assertInstanceOf(TestCustomClient::class, $engine);
    }

    public function testQueueManagerRejectsInvalidClientClass(): void
    {
        QueueManager::setConfig('test-bad-client', [
            'url' => 'null:',
            'queue' => 'default',
            'clientClass' => stdClass::class,
        ]);
        $this->expectException(InvalidArgumentException::class);
        QueueManager::engine('test-bad-client');
    }
}
