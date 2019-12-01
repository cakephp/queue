<?php
namespace Queue\Queue;

use BadMethodCallException;
use Cake\Log\Log;
use Cake\Utility\Hash;
use Enqueue\SimpleClient\SimpleClient;
use LogicException;

class Queue
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
     * Assuming that the class' name is `Queue` the following scenarios
     * are supported:
     *
     * Setting a queue engine up.
     *
     * ```
     * Queue::setConfig('default', $settings);
     * ```
     *
     * Injecting a constructed adapter in:
     *
     * ```
     * Queue::setConfig('default', $instance);
     * ```
     *
     * Configure multiple adapters at once:
     *
     * ```
     * Queue::setConfig($arrayOfConfig);
     * ```
     *
     * @param string|array $key The name of the configuration, or an array of multiple configs.
     * @param array $config An array of name => configuration data for adapter.
     * @throws \BadMethodCallException When trying to modify an existing config.
     * @throws \LogicException When trying to store an invalid structured config array.
     * @return void
     */
    public static function setConfig($key, $config = null)
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
            throw new BadMethodCallException(sprintf('Cannot reconfigure existing key "%s"', $key));
        }

        if (empty($config['url'])) {
            throw new BadMethodCallException('Must specify "url" key');
        }

        static::$_config[$key] = $config;
    }

    /**
     * Reads existing configuration.
     *
     * @param string $key The name of the configuration.
     * @return mixed Configuration data at the named key or null if the key does not exist.
     */
    public static function getConfig($key)
    {
        return isset(static::$_config[$key]) ? static::$_config[$key] : null;
    }

    /**
     * Get a queueing engine
     *
     * @param string $name Key name of a configured adapter to get.
     * @return \Enqueue\SimpleClient\SimpleClient
     */
    public static function engine($name)
    {
        if (isset(static::$_clients[$name])) {
            return static::$_clients[$name];
        }

        $config = static::getConfig($name);
        $url = Hash::get($config, 'url');
        $loggerName = Hash::get($config, 'logger', 'debug');
        $logger = Log::engine($loggerName);

        static::$_clients[$name] = new SimpleClient($url, $logger);
        static::$_clients[$name]->setupBroker();
        return static::$_clients[$name];
    }

    /**
     * Push a single job onto the queue.
     *
     * @param callable $callable    a job callable
     * @param array  $args        an array of data to set for the job
     * @param array  $options     an array of options for publishing the job
     * @return void
     */
    public static function push($callable, array $args = [], array $options = []): void
    {
        $name = Hash::get($options, 'config', 'default');
        $config = static::getConfig($name);
        $queue = Hash::get($config, 'queue', 'default');

        $item = [
            'queue' => $queue,
            'class' => $callable,
            'args'  => [$args],
            'id'    => md5(uniqid('', true)),
            'queue_time' => microtime(true),
        ];

        $client = static::engine($name);
        $client->sendEvent($queue, $item);
    }
}
