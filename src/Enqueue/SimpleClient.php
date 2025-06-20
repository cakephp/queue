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
namespace Cake\Queue\Enqueue;

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
use Enqueue\Symfony\Client\DependencyInjection\ClientFactory;
use Enqueue\Symfony\DependencyInjection\TransportFactory;
use Interop\Queue\Processor;
use JsonSerializable;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor as ConfigProcessor;

class SimpleClient
{
    /**
     * @var \Enqueue\Client\DriverInterface
     */
    private DriverInterface $driver;

    /**
     * @var \Enqueue\Client\Producer
     */
    private Producer $producer;

    /**
     * @var \Enqueue\Consumption\QueueConsumer
     */
    private QueueConsumer $queueConsumer;

    /**
     * @var \Enqueue\ArrayProcessorRegistry
     */
    private ArrayProcessorRegistry $processorRegistry;

    /**
     * @var \Enqueue\Client\DelegateProcessor
     */
    private DelegateProcessor $delegateProcessor;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * The config could be a transport DSN (string) or an array, here's an example of a few DSNs:.
     *
     * $config = amqp:
     * $config = amqp://guest:guest@localhost:5672/%2f?lazy=1&persisted=1
     * $config = file://foo/bar/
     * $config = null:
     *
     * or an array:
     *
     * $config = [
     *   'transport' => [
     *      'dsn' => 'amqps://guest:guest@localhost:5672/%2f',
     *      'ssl_cacert' => '/a/dir/cacert.pem',
     *      'ssl_cert' => '/a/dir/cert.pem',
     *      'ssl_key' => '/a/dir/key.pem',
     * ]
     *
     * with custom connection factory class
     *
     * $config = [
     *   'transport' => [
     *      'dsn' => 'amqps://guest:guest@localhost:5672/%2f',
     *      'connection_factory_class' => 'aCustomConnectionFactory',
     *      // other options available options are factory_class and factory_service
     * ]
     *
     * The client config
     *
     * $config = [
     *   'transport' => 'null:',
     *   'client' => [
     *     'prefix'                   => 'enqueue',
     *     'separator'                => '.',
     *     'app_name'                 => 'app',
     *     'router_topic'             => 'router',
     *     'router_queue'             => 'default',
     *     'default_queue'            => 'default',
     *     'redelivered_delay_time'   => 0
     *   ],
     *   'extensions' => [
     *     'signal_extension' => true,
     *     'reply_extension' => true,
     *   ]
     * ]
     *
     * @param array|string $config
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(string|array $config, ?LoggerInterface $logger = null)
    {
        if (is_string($config)) {
            $config = [
                'transport' => $config,
                'client' => true,
            ];
        }

        $this->logger = $logger ?: new NullLogger();

        $this->build(['enqueue' => $config]);
    }

    /**
     * @param string $topic
     * @param \Interop\Queue\Processor|callable $processor
     * @param string|null $processorName
     */
    public function bindTopic(string $topic, callable|Processor $processor, ?string $processorName = null): void
    {
        if (is_callable($processor)) {
            $processor = new CallbackProcessor($processor);
        }

        if (!$processor instanceof Processor) {
            throw new LogicException('The processor must be either callable or instance of Processor');
        }

        $processorName = $processorName ?: uniqid($processor::class);

        $this->driver->getRouteCollection()->add(new Route($topic, Route::TOPIC, $processorName));
        $this->processorRegistry->add($processorName, $processor);
    }

    /**
     * @param string $command
     * @param \Interop\Queue\Processor|callable $processor
     * @param string|null $processorName
     */
    public function bindCommand(string $command, callable|Processor $processor, ?string $processorName = null): void
    {
        if (is_callable($processor)) {
            $processor = new CallbackProcessor($processor);
        }

        if (!$processor instanceof Processor) {
            throw new LogicException('The processor must be either callable or instance of Processor');
        }

        $processorName = $processorName ?: uniqid($processor::class);

        $this->driver->getRouteCollection()->add(new Route($command, Route::COMMAND, $processorName));
        $this->processorRegistry->add($processorName, $processor);
    }

    /**
     * @param string $command
     * @param \JsonSerializable|\Enqueue\Client\Message|array|string $message
     * @param bool $needReply
     * @return \Enqueue\Rpc\Promise|null
     * @throws \Exception
     */
    public function sendCommand(
        string $command,
        string|array|JsonSerializable|Message $message,
        bool $needReply = false,
    ): ?Promise {
        return $this->producer->sendCommand($command, $message, $needReply);
    }

    /**
     * @param string $topic
     * @param \Enqueue\Client\Message|array|string $message
     * @throws \Exception
     */
    public function sendEvent(string $topic, string|array|Message $message): void
    {
        $this->producer->sendEvent($topic, $message);
    }

    /**
     * @param \Enqueue\Consumption\ExtensionInterface|null $runtimeExtension
     * @return void
     * @throws \Exception
     */
    public function consume(?ExtensionInterface $runtimeExtension = null): void
    {
        $this->setupBroker();

        $boundQueues = [];

        $routerQueue = $this->getDriver()->createQueue($this->getDriver()->getConfig()->getRouterQueue());
        $this->queueConsumer->bind($routerQueue, $this->delegateProcessor);
        $boundQueues[$routerQueue->getQueueName()] = true;

        foreach ($this->driver->getRouteCollection()->all() as $route) {
            $queue = $this->getDriver()->createRouteQueue($route);
            if (array_key_exists($queue->getQueueName(), $boundQueues)) {
                continue;
            }

            $this->queueConsumer->bind($queue, $this->delegateProcessor);

            $boundQueues[$queue->getQueueName()] = true;
        }

        $this->queueConsumer->consume($runtimeExtension);
    }

    /**
     * @return \Enqueue\Consumption\QueueConsumerInterface
     */
    public function getQueueConsumer(): QueueConsumerInterface
    {
        return $this->queueConsumer;
    }

    /**
     * @return \Enqueue\Client\DriverInterface
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * @param bool $setupBroker
     * @return \Enqueue\Client\ProducerInterface
     */
    public function getProducer(bool $setupBroker = false): ProducerInterface
    {
        $setupBroker && $this->setupBroker();

        return $this->producer;
    }

    /**
     * @return \Enqueue\Client\DelegateProcessor
     */
    public function getDelegateProcessor(): DelegateProcessor
    {
        return $this->delegateProcessor;
    }

    /**
     * @return void
     */
    public function setupBroker(): void
    {
        $this->getDriver()->setupBroker();
    }

    /**
     * @param array $configs
     * @return void
     */
    public function build(array $configs): void
    {
        $configProcessor = new ConfigProcessor();
        $simpleClientConfig = $configProcessor->process($this->createConfiguration(), $configs);

        if (isset($simpleClientConfig['transport']['factory_service'])) {
            throw new LogicException('transport.factory_service option is not supported by simple client');
        }
        if (isset($simpleClientConfig['transport']['factory_class'])) {
            throw new LogicException('transport.factory_class option is not supported by simple client');
        }
        if (isset($simpleClientConfig['transport']['connection_factory_class'])) {
            throw new LogicException('transport.connection_factory_class option is not supported by simple client');
        }

        $connectionFactoryFactory = new ConnectionFactoryFactory();
        $connection = $connectionFactoryFactory->create($simpleClientConfig['transport']);

        $clientExtensions = new ClientChainExtensions([]);

        $config = new Config(
            $simpleClientConfig['client']['prefix'],
            $simpleClientConfig['client']['separator'],
            $simpleClientConfig['client']['app_name'],
            $simpleClientConfig['client']['router_topic'],
            $simpleClientConfig['client']['router_queue'],
            $simpleClientConfig['client']['default_queue'],
            'enqueue.client.router_processor',
            $simpleClientConfig['transport'],
            [],
        );

        $routeCollection = new RouteCollection([]);
        $driverFactory = new DriverFactory();

        $driver = $driverFactory->create(
            $connection,
            $config,
            $routeCollection,
        );

        $rpcFactory = new RpcFactory($driver->getContext());

        $producer = new Producer($driver, $rpcFactory, $clientExtensions);

        $processorRegistry = new ArrayProcessorRegistry([]);

        $delegateProcessor = new DelegateProcessor($processorRegistry);

        // consumption extensions
        $consumptionExtensions = [];
        if ($simpleClientConfig['client']['redelivered_delay_time']) {
            $consumptionExtensions[] = new DelayRedeliveredMessageExtension(
                $driver,
                $simpleClientConfig['client']['redelivered_delay_time'],
            );
        }

        if ($simpleClientConfig['extensions']['signal_extension']) {
            $consumptionExtensions[] = new SignalExtension();
        }

        if ($simpleClientConfig['extensions']['reply_extension']) {
            $consumptionExtensions[] = new ReplyExtension();
        }

        $consumptionExtensions[] = new SetRouterPropertiesExtension($driver);
        $consumptionExtensions[] = new LogExtension();

        $consumptionChainExtension = new ConsumptionChainExtension($consumptionExtensions);
        $queueConsumer = new QueueConsumer($driver->getContext(), $consumptionChainExtension, [], $this->logger);

        $routerProcessor = new RouterProcessor($driver);

        $processorRegistry->add($config->getRouterProcessor(), $routerProcessor);

        $this->driver = $driver;
        $this->producer = $producer;
        $this->queueConsumer = $queueConsumer;
        $this->delegateProcessor = $delegateProcessor;
        $this->processorRegistry = $processorRegistry;
    }

    /**
     * @return \Symfony\Component\Config\Definition\NodeInterface
     */
    private function createConfiguration(): NodeInterface
    {
        $tb = new TreeBuilder('enqueue');
        $rootNode = $tb->getRootNode();

        $rootNode
            ->beforeNormalization()
            ->ifEmpty()->then(function () {
                return ['transport' => ['dsn' => 'null:']];
            });

        $rootNode
            ->append(TransportFactory::getConfiguration())
            ->append(TransportFactory::getQueueConsumerConfiguration())
            ->append(ClientFactory::getConfiguration(false));

        $rootNode->children()
            ->arrayNode('extensions')->addDefaultsIfNotSet()->children()
            ->booleanNode('signal_extension')->defaultValue(function_exists('pcntl_signal_dispatch'))->end()
            ->booleanNode('reply_extension')->defaultTrue()->end()
            ->end();

        return $tb->buildTree();
    }
}
