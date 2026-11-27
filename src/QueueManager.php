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
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue;

use BadMethodCallException;
use Cake\Cache\Cache;
use Cake\Core\App;
use Cake\Log\Log;
use Cake\Queue\Bus\BusFactory;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\DispatchResult;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\NonSendableStampInterface;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\StampInterface;
use Cake\Queue\Client\EnqueueClient;
use Cake\Queue\Dto\DtoManager;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerInterface;

class QueueManager
{
    /**
     * Configuration sets.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $_config = [];

    /**
     * Queue clients
     *
     * @var array<string, \Cake\Queue\Client\EnqueueClient>
     */
    protected static array $_clients = [];

    /**
     * PSR-11 container used to resolve queue services.
     *
     * Set automatically from `QueuePlugin::services()` when the application
     * uses dependency injection. Any PSR-11 container is accepted for
     * storage and engine wiring, but service resolution (bus middleware,
     * jobs, listeners) requires a `Cake\Core\ContainerInterface`
     * instance: other containers are ignored there and classes are
     * instantiated directly.
     */
    protected static ?ContainerInterface $container = null;

    /**
     * Set the PSR-11 container used to resolve queue services.
     *
     * Cached clients are dropped when the container instance changes
     * so that already built clients never keep a stale container. Buses
     * themselves are rebuilt on every use (`BusFactory::reset()` is a
     * no-op kept for backward compatibility). Bus
     * middleware, jobs and listeners resolve through it only when it is a
     * `Cake\Core\ContainerInterface` instance.
     *
     * @param \Psr\Container\ContainerInterface|null $container Container instance or null to detach.
     * @return void
     */
    public static function setContainer(?ContainerInterface $container): void
    {
        if ($container !== static::$container) {
            static::$_clients = [];
            BusFactory::reset();
        }

        static::$container = $container;
    }

    /**
     * Get the PSR-11 container used to resolve queue services.
     *
     * @return \Psr\Container\ContainerInterface|null Container instance or null when unavailable.
     */
    public static function getContainer(): ?ContainerInterface
    {
        return static::$container;
    }

    /**
     * This method can be used to define configuration adapters for an application.
     *
     * To change an adapter's configuration at runtime, first drop the adapter and then
     * reconfigure it.
     *
     * Adapters will not be constructed until the first operation is done.
     *
     * ### Usage
     *
     * Assuming that the class' name is `QueueManager` the following scenarios
     * are supported:
     *
     * Setting a queue engine up.
     *
     * ```
     * QueueManager::setConfig('default', $settings);
     * ```
     *
     * Injecting a constructed adapter in:
     *
     * ```
     * QueueManager::setConfig('default', $instance);
     * ```
     *
     * Configure multiple adapters at once:
     *
     * ```
     * QueueManager::setConfig($arrayOfConfig);
     * ```
     *
     * @param array<string, array<string, mixed>>|string $key The name of the configuration, or an array of multiple configs.
     * @param array<string, mixed>|null $config An array of name => configuration data for adapter.
     * @throws \BadMethodCallException When trying to modify an existing config.
     * @throws \LogicException When trying to store an invalid structured config array.
     * @return void
     */
    public static function setConfig(string|array $key, ?array $config = null): void
    {
        if ($config === null) {
            if (!is_array($key)) {
                throw new LogicException('If config is null, key must be an array.');
            }

            foreach ($key as $name => $settings) {
                static::setConfig($name, $settings);
            }

            return;
        }

        if (is_array($key)) {
            throw new LogicException('If config is not null, key must be a string.');
        }

        if (isset(static::$_config[$key])) {
            throw new BadMethodCallException(sprintf('Cannot reconfigure existing key `%s`', $key));
        }

        if (empty($config['url'])) {
            throw new BadMethodCallException('Must specify `url` key.');
        }

        if (!empty($config['queue'])) {
            if (!is_array($config['url'])) {
                $config['url'] = [
                    'transport' => $config['url'],
                    'client' => [
                        'router_topic' => $config['queue'],
                        'router_queue' => $config['queue'],
                        'default_queue' => $config['queue'],
                    ],
                ];
            } else {
                $clientConfig = $config['url']['client'] ?? [];
                $config['url']['client'] = $clientConfig + [
                    'router_topic' => $config['queue'],
                    'router_queue' => $config['queue'],
                    'default_queue' => $config['queue'],
                ];
            }
        }

        if (!empty($config['uniqueCache'])) {
            $cacheDefaults = [
                'duration' => '+24 hours',
            ];

            $cacheConfig = [...$cacheDefaults, ...$config['uniqueCache']];

            $config['uniqueCacheKey'] = 'Cake/Queue.queueUnique.' . $key;

            Cache::setConfig($config['uniqueCacheKey'], $cacheConfig);
        }

        static::$_config[$key] = $config;
    }

    /**
     * Reads existing configuration.
     *
     * @param string $key The name of the configuration.
     * @return array<string, mixed>|null Configuration data at the named key or null if the key does not exist.
     */
    public static function getConfig(string $key): ?array
    {
        return static::$_config[$key] ?? null;
    }

    /**
     * Get the configured queue keys.
     *
     * @return array<int, string> List of configured queue configuration keys.
     */
    public static function configured(): array
    {
        return array_keys(static::$_config);
    }

    /**
     * Remove a configured queue adapter.
     *
     * @param string $key The config name to drop.
     * @return void
     */
    public static function drop(string $key): void
    {
        unset(static::$_clients[$key], static::$_config[$key]);
        BusFactory::reset($key);
    }

    /**
     * Get a queueing engine
     *
     * A per-call container overrides the static one for the initial build only.
     * Already cached clients are reused as is, call `drop()` to rebuild them.
     *
     * @param string $name Key name of a configured adapter to get.
     * @param \Psr\Container\ContainerInterface|null $container Optional PSR-11 container, falls back to `getContainer()`.
     * @return \Cake\Queue\Client\EnqueueClient
     */
    public static function engine(string $name, ?ContainerInterface $container = null): EnqueueClient
    {
        if (isset(static::$_clients[$name])) {
            return static::$_clients[$name];
        }

        $config = static::getConfig($name);
        if ($config === null) {
            throw new InvalidArgumentException(sprintf('Queue config `%s` not found.', $name));
        }

        $config += [
            'logger' => null,
            'receiveTimeout' => null,
            'clientClass' => EnqueueClient::class,
        ];

        $logger = $config['logger'] ? Log::engine($config['logger']) : null;

        $clientClass = $config['clientClass'];
        if (!is_string($clientClass) || !class_exists($clientClass)) {
            throw new InvalidArgumentException(sprintf('Client class `%s` not found.', is_string($clientClass) ? $clientClass : gettype($clientClass)));
        }

        if (!is_a($clientClass, EnqueueClient::class, true)) {
            throw new InvalidArgumentException(sprintf('Client class `%s` must extend `%s`.', $clientClass, EnqueueClient::class));
        }

        $resolvedContainer = $container ?? static::$container;
        static::$_clients[$name] = new $clientClass($config['url'], $logger, $resolvedContainer);
        static::$_clients[$name]->setupBroker();

        if ($config['receiveTimeout'] !== null) {
            static::$_clients[$name]->getQueueConsumer()->setReceiveTimeout($config['receiveTimeout']);
        }

        return static::$_clients[$name];
    }

    /**
     * Push a single job onto the queue.
     *
     * @param array<int, string>|string $className The classname of a job that implements the
     *   \Cake\Queue\Job\JobInterface. The class will be constructed through the
     *   container when available (or directly otherwise) and the job
     *   dispatched through the dispatch bus.
     * @param array<string, mixed>|object $data An array of data or a DTO object that will
     *   be passed to the job. When a DTO object is given it is serialized and the class
     *   name is stored as message metadata (uniqueness / debugging). Jobs must still
     *   pass the expected class to `Message::getDto()`.
     * @param array<string, mixed> $options An array of options for publishing the job:
     *   - `config` - A queue config name. Defaults to 'default'.
     *   - `delay` - Time (in integer seconds) to delay message, after which it
     *      will be processed. Not all message brokers accept this. Default `null`.
     *   - `dtoClass` - Optional DTO class metadata recorded on the message body.
     *     Only needed when `$data` is an array. Ignored when `$data` is already a DTO
     *     object. Does not control hydration. The job passes the expected class to
     *     `Message::getDto()`. Default `null`.
     *   - `expires` - Time (in integer seconds) after which the message expires.
     *     The message will be removed from the queue if this time is exceeded
     *     and it has not been consumed. Default `null`.
     *   - `metadata` - Optional envelope data recorded on the message body next
     *     to `data` (e.g. `tags`, `_uniqueId`, `batch_id`). Unlike `data` it is
     *     never passed to the job or hydrated into a DTO. Omitted from the body
     *     when empty or not an array. The `stamps` key is reserved for the bus
     *     and must not be set here; use the `stamps` option instead. Default `[]`.
     *   - `stamps` - Optional list of `Cake\Queue\Bus\StampInterface` carried by
     *     the dispatch envelope and written to the `metadata.stamps` list.
     *     In-memory machinery stamps (`NonSendableStampInterface`,
     *     `ReceivedStamp`) are rejected. The `metadata` passes through
     *     verbatim, except for the reserved `stamps` key. Default `[]`.
     *   - `priority` - Valid values:
     *      - `\Enqueue\Client\MessagePriority::VERY_LOW`
     *      - `\Enqueue\Client\MessagePriority::LOW`
     *      - `\Enqueue\Client\MessagePriority::NORMAL`
     *      - `\Enqueue\Client\MessagePriority::HIGH`
     *      - `\Enqueue\Client\MessagePriority::VERY_HIGH`
     *   - `queue` - The name of a queue to use, from queue `config` array or
     *      string 'default' if empty.
     * @return \Cake\Queue\Bus\DispatchResult Dispatch outcome: transport message
     *   identity (null when the transport assigns none) and whether the dispatch
     *   was skipped as a duplicate unique job.
     * @throws \LogicException When the dispatch pipeline is short-circuited without sending.
     */
    public static function push(string|array $className, array|object $data = [], array $options = []): DispatchResult
    {
        [$class, $method] = is_array($className) ? $className : [$className, 'execute'];

        $class = App::className($class, 'Job', 'Job');
        if (is_null($class)) {
            throw new InvalidArgumentException(sprintf('`%s` class does not exist.', $class));
        }

        $dtoClass = null;
        if (is_object($data)) {
            $dtoClass = $data::class;
            $data = DtoManager::serialize($data);
        } elseif (!empty($options['dtoClass'])) {
            $dtoClass = $options['dtoClass'];
            $data = DtoManager::serialize($data);
        }

        $name = $options['config'] ?? 'default';

        $config = static::getConfig($name);
        if ($config === null) {
            throw new InvalidArgumentException(sprintf('Queue config `%s` not found.', $name));
        }

        $config += [
            'logger' => null,
        ];

        $queue = $options['queue'] ?? $config['queue'] ?? 'default';

        $stamps = $options['stamps'] ?? [];
        if (!is_array($stamps)) {
            throw new InvalidArgumentException('The `stamps` option must be a list of `StampInterface`.');
        }

        foreach ($stamps as $stamp) {
            if (!$stamp instanceof StampInterface) {
                throw new InvalidArgumentException(sprintf(
                    'The `stamps` option must be a list of `StampInterface`, `%s` given.',
                    get_debug_type($stamp),
                ));
            }

            if ($stamp instanceof ReceivedStamp) {
                throw new InvalidArgumentException(
                    'The `stamps` option must not contain `ReceivedStamp`: it marks consumed envelopes.',
                );
            }

            if ($stamp instanceof NonSendableStampInterface) {
                throw new InvalidArgumentException(sprintf(
                    'The `stamps` option must not contain `%s`: it never leaves the process.',
                    $stamp::class,
                ));
            }
        }

        $metadata = $options['metadata'] ?? null;
        if ($metadata !== null && !is_array($metadata)) {
            throw new InvalidArgumentException('The `metadata` option must be an array or null.');
        }

        if (!is_array($metadata)) {
            $metadata = [];
        }

        if (array_key_exists('stamps', $metadata)) {
            throw new InvalidArgumentException(
                'The `metadata` option must not contain a `stamps` key: ' .
                'it is reserved for the bus. Use the `stamps` option instead.',
            );
        }

        $dispatch = new Dispatch(
            $class,
            $method,
            $data,
            $dtoClass,
            $queue,
            $name,
            $options['delay'] ?? null,
            $options['expires'] ?? null,
            $options['priority'] ?? null,
            $metadata,
        );
        $envelope = new Envelope($dispatch, $stamps);
        $finished = BusFactory::dispatchBus($name)->handle($envelope);

        return DispatchResult::fromEnvelope($finished);
    }

    /**
     * @param class-string $class Class name
     * @param string $method Method name
     * @param array<string, mixed> $data Message data
     * @param class-string|null $dtoClass The DTO class the data was dispatched with, if any. Two
     *   dispatches with identical `$data` but different `$dtoClass` are treated as distinct so a
     *   coincidental structural match between unrelated DTOs does not collapse into one dedupe entry.
     */
    public static function getUniqueId(string $class, string $method, array $data, ?string $dtoClass = null): string
    {
        $data = static::sortUniqueValues($data);

        $hashInput = implode('', [
            $class,
            $method,
            $dtoClass ?? '',
            json_encode($data),
        ]);

        return hash('md5', $hashInput);
    }

    /**
     * Recursively sort an array by key
     *
     * @param array<string, mixed> $data The data to sort
     * @return array<string, mixed> The sorted array
     */
    protected static function sortUniqueValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::sortUniqueValues($value);
            }
        }

        ksort($data);

        return $data;
    }
}
