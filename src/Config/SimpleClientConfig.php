<?php
namespace Cake\Queue\Config;

class SimpleClientConfig

{

    private $simpleClientConfig = [];


    public function __construct($queue, $config)
    {

        $this->createConfig($queue, $config);
    }

    public function get()
    {
        return $this->simpleClientConfig;
    }
    public function createConfig($queue, $config)
    {

        $queue = $queue !== 'default' ? $queue : $config['queue'];

        $this->simpleClientConfig = [
            'transport' => $config['url'],
            'client' =>
            [
                'prefix'                   => 'enqueue',
                'separator'                => '.',
                'app_name'                 => 'app',
                'router_topic'             =>   $queue,
                'router_queue'             =>   $queue,
                'default_queue'            =>   $queue,
            ],
            'extensions' => [
                'signal_extension' => false,
                'reply_extension' => false,
            ]
        ];
    }
}
