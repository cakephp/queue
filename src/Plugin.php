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

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Queue\Command\JobCommand;
use Cake\Queue\Command\PurgeFailedCommand;
use Cake\Queue\Command\RequeueCommand;
use Cake\Queue\Command\WorkerCommand;
use InvalidArgumentException;

/**
 * Plugin for Queue
 */
class Plugin extends BasePlugin
{
    /**
     * Plugin name.
     */
    protected ?string $name = 'Cake/Queue';

    /**
     * Load routes or not
     */
    protected bool $routesEnabled = false;

    /**
     * Load the Queue configuration
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        if (!Configure::read('Queue')) {
            throw new InvalidArgumentException(
                'Missing `Queue` configuration key, please check the CakePHP Queue documentation' .
                ' to complete the plugin setup.'
            );
        }

        foreach (Configure::read('Queue') as $key => $data) {
            if (QueueManager::getConfig($key) === null) {
                QueueManager::setConfig($key, $data);
            }
        }
    }

    /**
     * Add console commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        if (class_exists('Bake\Command\SimpleBakeCommand')) {
            $commands->add('bake job', JobCommand::class);
        }

        return $commands
            ->add('queue worker', WorkerCommand::class)
            ->add('worker', WorkerCommand::class)
            ->add('queue requeue', RequeueCommand::class)
            ->add('queue purge_failed', PurgeFailedCommand::class);
    }

    /**
     * Add DI container to Worker command
     *
     * @param \Cake\Core\ContainerInterface $container The DI container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(ContainerInterface::class, $container);
        $container
            ->add(WorkerCommand::class)
            ->addArgument(ContainerInterface::class);
    }
}
