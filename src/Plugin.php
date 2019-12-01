<?php

namespace Queue;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Queue\Queue\Queue;

/**
 * Plugin for Queue
 */
class Plugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected $name = 'Queue';

    /**
     * Load the Queue configuration
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app)
    {
        Queue::setConfig(Configure::read('Queue'));
    }
}
