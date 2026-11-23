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
namespace Cake\Queue\Client;

use Cake\Event\EventDispatcherTrait;
use Enqueue\ArrayProcessorRegistry;
use Enqueue\Client\ChainExtension as ClientChainExtensions;
use Enqueue\Client\Config;
use Enqueue\Client\ConsumptionExtension\DelayRedeliveredMessageExtension;
use Enqueue\Client\ConsumptionExtension\LogExtension;
use Enqueue\Client\ConsumptionExtension\SetRouterPropertiesExtension;
use Enqueue\Client\DelegateProcessor;
use Enqueue\Client\DriverFactory;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\Message;
use Enqueue\Client\Producer;
use Enqueue\Client\ProducerInterface;
use Enqueue\Client\Route;
use Enqueue\Client\RouteCollection;
use Enqueue\Client\RouterProcessor;
use Enqueue\ConnectionFactoryFactory;
use Enqueue\Consumption\CallbackProcessor;
use Enqueue\Consumption\ChainExtension as ConsumptionChainExtension;
use Enqueue\Consumption\Extension\ReplyExtension;
use Enqueue\Consumption\Extension\SignalExtension;
use Enqueue\Consumption\ExtensionInterface;
use Enqueue\Consumption\QueueConsumer;
use Enqueue\Consumption\QueueConsumerInterface;
use Enqueue\Rpc\Promise;
use Enqueue\Rpc\RpcFactory;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Processor;
use JsonSerializable;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Queue client built on top of Enqueue transports without Symfony config.
 *
 * Assembles connection, driver, producer, processor registry and queue consumer
 * from a plain PHP array. Emits produce success and failure events.
 */
class EnqueueClient
{
    use EventDispatcherTrait;

    /**
     * Queue driver instance.
     */
    protected DriverInterface $driver;

    /**
     * Queue producer instance.
     */
    protected ProducerInterface $producer;

    /**
     * Queue consumer instance.
     */
    protected QueueConsumer $queueConsumer;

    /**
     * Processor registry instance.
     */
    protected ArrayProcessorRegistry $processorRegistry;

    /**
     * Delegate processor instance.
     */
    protected DelegateProcessor $delegateProcessor;

    /**
     * Logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * PSR-11 container instance or null when unavailable.
     *
     * Only `has()` and `get()` are used, so any PSR-11 container is accepted.
     */
    protected ?ContainerInterface $container;

    /**
     * Post send extension capturing the last produce result.
     */
    protected EnqueuePostSendExtension $postSendExtension;

    /**
     * Create a new queue client instance.
     *
     * @param string|array<string, mixed> $config DSN string or ['transport'=>..., 'client'=>..., 'extensions'=>...]
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance.
     * @param \Psr\Container\ContainerInterface|null $container PSR-11 container used to resolve `connection_factory_class`.
     */
    public function __construct(
        string|array $config,
        ?LoggerInterface $logger = null,
        ?ContainerInterface $container = null,
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->container = $container;
        $this->build($config);
    }

    /**
     * Bind a processor to a topic.
     *
     * @param callable|\Interop\Queue\Processor $processor Processor callback or instance.
     * @param string $topic Topic name.
     * @param string|null $processorName Custom processor name.
     * @return void
     */
    public function bindTopic(string $topic, callable|Processor $processor, ?string $processorName = null): void
    {
        if (is_callable($processor)) {
            $processor = new CallbackProcessor($processor);
        }

        $processorName = $processorName ?: uniqid($processor::class);
        $this->driver->getRouteCollection()->add(new Route($topic, Route::TOPIC, $processorName));
        $this->processorRegistry->add($processorName, $processor);
    }

    /**
     * Bind a processor to a command.
     *
     * @param callable|\Interop\Queue\Processor $processor Processor callback or instance.
     * @param string $command Command name.
     * @param string|null $processorName Custom processor name.
     * @return void
     */
    public function bindCommand(string $command, callable|Processor $processor, ?string $processorName = null): void
    {
        if (is_callable($processor)) {
            $processor = new CallbackProcessor($processor);
        }

        $processorName = $processorName ?: uniqid($processor::class);
        $this->driver->getRouteCollection()->add(new Route($command, Route::COMMAND, $processorName));
        $this->processorRegistry->add($processorName, $processor);
    }

    /**
     * Send a command message and dispatch produce events.
     *
     * @param string $command Command name.
     * @param string|array|\JsonSerializable|\Enqueue\Client\Message $message Message body or instance.
     * @param bool $needReply Whether a reply is expected.
     * @return \Enqueue\Rpc\Promise|null Promise when a reply is expected, null otherwise.
     */
    public function sendCommand(string $command, string|array|JsonSerializable|Message $message, bool $needReply = false): ?Promise
    {
        $this->postSendExtension->clear();
        if (!$message instanceof Message) {
            $message = new Message($message);
        }

        try {
            $promise = $this->producer->sendCommand($command, $message, $needReply);
        } catch (Throwable $throwable) {
            $this->dispatchEvent('Queue.produce.failure', [
                'command' => $command,
                'exception' => $throwable,
            ]);
            throw $throwable;
        }

        $postSend = $this->postSendExtension->getLastPostSend();
        $this->dispatchEvent('Queue.produce.success', [
            'command' => $command,
            'messageId' => $postSend?->getMessage()->getMessageId(),
            'transportDestination' => $postSend?->getTransportDestination(),
            'promise' => $promise,
        ]);

        return $promise;
    }

    /**
     * Send an event message and dispatch produce events.
     *
     * @param string $topic Topic name.
     * @param string|array|\JsonSerializable|\Enqueue\Client\Message $message Message body or instance.
     * @return void
     */
    public function sendEvent(string $topic, string|array|JsonSerializable|Message $message): void
    {
        $this->postSendExtension->clear();
        if (!$message instanceof Message) {
            $message = new Message($message);
        }

        try {
            $this->producer->sendEvent($topic, $message);
        } catch (Throwable $throwable) {
            $this->dispatchEvent('Queue.produce.failure', [
                'topic' => $topic,
                'exception' => $throwable,
            ]);
            throw $throwable;
        }

        $postSend = $this->postSendExtension->getLastPostSend();
        $this->dispatchEvent('Queue.produce.success', [
            'topic' => $topic,
            'messageId' => $postSend?->getMessage()->getMessageId(),
            'transportDestination' => $postSend?->getTransportDestination(),
        ]);
    }

    /**
     * Consume messages from bound queues.
     *
     * @param \Enqueue\Consumption\ExtensionInterface|null $runtimeExtension Runtime extension.
     * @return void
     */
    public function consume(?ExtensionInterface $runtimeExtension = null): void
    {
        $this->setupBroker();

        $boundQueues = [];

        $routerQueue = $this->driver->createQueue($this->driver->getConfig()->getRouterQueue());
        $this->queueConsumer->bind($routerQueue, $this->delegateProcessor);
        $boundQueues[$routerQueue->getQueueName()] = true;

        foreach ($this->driver->getRouteCollection()->all() as $route) {
            $queue = $this->driver->createRouteQueue($route);
            if (array_key_exists($queue->getQueueName(), $boundQueues)) {
                continue;
            }

            $this->queueConsumer->bind($queue, $this->delegateProcessor);
            $boundQueues[$queue->getQueueName()] = true;
        }

        $this->queueConsumer->consume($runtimeExtension);
    }

    /**
     * Get queue consumer instance.
     *
     * @return \Enqueue\Consumption\QueueConsumerInterface Queue consumer.
     */
    public function getQueueConsumer(): QueueConsumerInterface
    {
        return $this->queueConsumer;
    }

    /**
     * Get driver instance.
     *
     * @return \Enqueue\Client\DriverInterface Driver.
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Get producer instance.
     *
     * Direct sends through the returned producer bypass the
     * `Queue.produce.success` and `Queue.produce.failure` events.
     * Use `sendEvent()` and `sendCommand()` for observable publishing.
     *
     * @param bool $setupBroker Whether to setup broker before returning producer.
     * @return \Enqueue\Client\ProducerInterface Producer.
     */
    public function getProducer(bool $setupBroker = false): ProducerInterface
    {
        if ($setupBroker) {
            $this->setupBroker();
        }

        return $this->producer;
    }

    /**
     * Get delegate processor instance.
     *
     * @return \Enqueue\Client\DelegateProcessor Delegate processor.
     */
    public function getDelegateProcessor(): DelegateProcessor
    {
        return $this->delegateProcessor;
    }

    /**
     * Setup broker queues and exchanges.
     *
     * @return void
     */
    public function setupBroker(): void
    {
        $this->driver->setupBroker();
    }

    /**
     * Build driver, producer and consumer from the given config.
     *
     * @param string|array<string, mixed> $config DSN string or normalized config array.
     * @return void
     */
    protected function build(string|array $config): void
    {
        $normalized = EnqueueClientConfig::normalize($config);

        $connection = $this->createConnection($normalized['transport']);

        $client = $normalized['client'];
        $configObj = new Config(
            $client['prefix'],
            $client['separator'],
            $client['app_name'],
            $client['router_topic'],
            $client['router_queue'],
            $client['default_queue'],
            EnqueueClientConfig::ROUTER_PROCESSOR,
            $normalized['transport'],
            $client['driver_options'] ?? [],
        );

        $routeCollection = new RouteCollection([]);
        $driver = (new DriverFactory())->create($connection, $configObj, $routeCollection);
        $this->postSendExtension = new EnqueuePostSendExtension();
        $producer = new Producer($driver, new RpcFactory($driver->getContext()), new ClientChainExtensions([$this->postSendExtension]));

        $processorRegistry = new ArrayProcessorRegistry([]);
        $delegateProcessor = new DelegateProcessor($processorRegistry);

        $consumptionExtensions = [];
        if (!empty($client['redelivered_delay_time'])) {
            $consumptionExtensions[] = new DelayRedeliveredMessageExtension($driver, (int)$client['redelivered_delay_time']);
        }

        if ($normalized['extensions']['signal_extension']) {
            $consumptionExtensions[] = new SignalExtension();
        }

        if ($normalized['extensions']['reply_extension']) {
            $consumptionExtensions[] = new ReplyExtension();
        }

        $consumptionExtensions[] = new SetRouterPropertiesExtension($driver);
        $consumptionExtensions[] = new LogExtension();

        $queueConsumer = new QueueConsumer(
            $driver->getContext(),
            new ConsumptionChainExtension($consumptionExtensions),
            [],
            $this->logger,
        );

        $processorRegistry->add($configObj->getRouterProcessor(), new RouterProcessor($driver));

        $this->driver = $driver;
        $this->producer = $producer;
        $this->queueConsumer = $queueConsumer;
        $this->delegateProcessor = $delegateProcessor;
        $this->processorRegistry = $processorRegistry;
    }

    /**
     * Create a connection factory from transport config.
     *
     * Supports a custom factory class directly or through a PSR-11 container.
     * The container path is tried first when a container is available and
     * contains the requested class, otherwise the class is instantiated
     * directly with the transport config array.
     *
     * @param array<string, mixed> $transport Transport config including DSN.
     * @return \Interop\Queue\ConnectionFactory Connection factory instance.
     * @throws \LogicException When the factory class cannot be resolved.
     */
    protected function createConnection(array $transport): ConnectionFactory
    {
        if (isset($transport['connection_factory_class'])) {
            $class = $transport['connection_factory_class'];
            if (!is_string($class) || $class === '') {
                throw new LogicException('Transport `connection_factory_class` must be a non-empty class name string.');
            }

            if ($this->container instanceof ContainerInterface && $this->container->has($class)) {
                $factory = $this->container->get($class);
                if (!$factory instanceof ConnectionFactory) {
                    throw new LogicException(sprintf('Service `%s` must implement `%s`.', $class, ConnectionFactory::class));
                }

                return $factory;
            }

            if (!is_a($class, ConnectionFactory::class, true)) {
                throw new LogicException(sprintf('Connection factory class `%s` must implement `%s`.', $class, ConnectionFactory::class));
            }

            return new $class($transport);
        }

        if (isset($transport['factory_service']) || isset($transport['factory_class'])) {
            throw new LogicException('transport.factory_service and transport.factory_class are not supported by EnqueueClient prototype. Use connection_factory_class.');
        }

        return (new ConnectionFactoryFactory())->create($transport);
    }
}
