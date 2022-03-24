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
use Cake\Core\App;
use Cake\Log\Log;
use Enqueue\Client\Message as ClientMessage;
use Enqueue\SimpleClient\SimpleClient;
use InvalidArgumentException;
use LogicException;

class QueueManager
{
    /**
     * Configuration sets.
     *
     * @var array
     */
    protected static $_config = [];

    /**
     * Queue clients
     *
     * @var array
     */
    protected static $_clients = [];

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
     * @param string|array $key The name of the configuration, or an array of multiple configs.
     * @param array $config An array of name => configuration data for adapter.
     * @throws \BadMethodCallException When trying to modify an existing config.
     * @throws \LogicException When trying to store an invalid structured config array.
     * @return void
     */
    public static function setConfig($key, $config = null): void
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

        if (isset(static::$_config[$key])) {
            /** @psalm-suppress PossiblyInvalidArgument */
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

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        static::$_config[$key] = $config;
    }

    /**
     * Reads existing configuration.
     *
     * @param string $key The name of the configuration.
     * @return mixed Configuration data at the named key or null if the key does not exist.
     */
    public static function getConfig(string $key)
    {
        return static::$_config[$key] ?? null;
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
    }

    /**
     * Get a queueing engine
     *
     * @param string $name Key name of a configured adapter to get.
     * @return \Enqueue\SimpleClient\SimpleClient
     */
    public static function engine(string $name): SimpleClient
    {
        if (isset(static::$_clients[$name])) {
            return static::$_clients[$name];
        }

        $config = static::getConfig($name) + [
            'logger' => null,
            'receiveTimeout' => null,
        ];

        $logger = $config['logger'] ? Log::engine($config['logger']) : null;

        static::$_clients[$name] = new SimpleClient($config['url'], $logger);
        static::$_clients[$name]->setupBroker();

        if (!is_null($config['receiveTimeout'])) {
            static::$_clients[$name]->getQueueConsumer()->setReceiveTimeout($config['receiveTimeout']);
        }

        return static::$_clients[$name];
    }

    /**
     * Push a single job onto the queue.
     *
     * @param string|string[] $className The classname of a job that implements the
     *   \Cake\Queue\Job\JobInterface. The class will be constructed by
     *   \Cake\Queue\Processor and have the execute method invoked.
     * @param array $data An array of data that will be passed to the job.
     * @param array $options An array of options for publishing the job:
     *   - `config` - A queue config name. Defaults to 'default'.
     *   - `delay` - Time (in integer seconds) to delay message, after which it
     *      will be processed. Not all message brokers accept this. Default `null`.
     *   - `expires` - Time (in integer seconds) after which the message expires.
     *     The message will be removed from the queue if this time is exceeded
     *     and it has not been consumed. Default `null`.
     *   - `priority` - Valid values:
     *      - `\Enqueue\Client\MessagePriority::VERY_LOW`
     *      - `\Enqueue\Client\MessagePriority::LOW`
     *      - `\Enqueue\Client\MessagePriority::NORMAL`
     *      - `\Enqueue\Client\MessagePriority::HIGH`
     *      - `\Enqueue\Client\MessagePriority::VERY_HIGH`
     *   - `queue` - The name of a queue to use, from queue `config` array or
     *      string 'default' if empty.
     * @return void
     */
    public static function push($className, array $data = [], array $options = []): void
    {
        [$class, $method] = is_array($className) ? $className : [$className, 'execute'];

        $class = App::className($class, 'Job', 'Job');
        if (is_null($class)) {
            throw new InvalidArgumentException("`$class` class does not exist.");
        }

        $options += [
            'config' => 'default',
            'queue' => 'default',
            'delay' => null,
            'expires' => null,
            'priority' => null,
        ];

        $name = $options['config'];

        $config = static::getConfig($name);
        $queue = $config['queue'] ?? 'default';

        $message = new ClientMessage([
            'class' => [$class, $method],
            'args' => [$data],
            'data' => $data,
        ]);

        if ($options['delay']) {
            $message->setDelay($options['delay']);
        }

        if ($options['expires']) {
            $message->setExpire($options['expires']);
        }

        if ($options['priority']) {
            $message->setPriority($options['priority']);
        }

        $client = static::engine($name);
        $client->sendEvent($queue, $message);
    }
}
