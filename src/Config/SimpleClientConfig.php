<?php
declare(strict_types=1);

namespace Cake\Queue\Config;

class SimpleClientConfig
{
    private $simpleClientConfig;

    private $queue = 'default';

    /**
     * __construct
     *
     * @param  string $queue The name of the queue drawn from Queue.{configname}.queue
     * (where {configname} is the configuration name)
     * or bin/cake worker -Q queuename
     * @param  mixed $config the Queue.{configname} array
     * @return void
     */
    public function __construct(string $queue, array $config)
    {
        $this->queue = $queue !== 'default' ? $queue : $config['queue'];

        $this->simpleClientConfig = $config['useConfigArray'] ? $this->buildConfig($config) : $config['url'];
    }

    /**
     * buildConfig
     *
     * @param  array $config The Queue.%s array
     * @return array
     */
    private function buildConfig(array $config): array
    {
        $newConfig = array_replace_recursive($config['configArray'], [
            'transport' => $config['url'],
            'client' =>
            [
                'router_topic' => $this->queue,
                'router_queue' => $this->queue,
                'default_queue' => $this->queue,
            ],
        ]);

        return $newConfig;
    }

    /**
     * get
     * return the SimpleClientConfig array
     *
     * @return mixed either the dsn string or a config array
     */
    public function getConfig()
    {
        return $this->simpleClientConfig;
    }

    /**
     * getQueue
     * Return the queue name
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }
}
