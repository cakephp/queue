<?php
declare(strict_types=1);

namespace Queue;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;

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
    public function bootstrap(PluginApplicationInterface $app): void
    {
        QueueManager::setConfig(Configure::read('Queue'));
    }
}
