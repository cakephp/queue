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

namespace Cake\Queue\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Queue\Config\SimpleClientConfig;
use Cake\Queue\Consumption\QueueExtension;
use Cake\Queue\Queue\Processor;
use Enqueue\SimpleClient\SimpleClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Worker command.
 */
class WorkerCommand extends Command
{
    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();

        $parser->addOption('config', [
            'default' => 'default',
            'help' => 'Name of a queue config to use',
            'short' => 'c',
        ]);
        $parser->addOption('queue', [
            'default' => 'default',
            'help' => 'Name of queue to bind to',
            'short' => 'Q',
        ]);
        $parser->addOption('processor', [
            'help' => 'Name of processor to bind to',
            'default' => null,
            'short' => 'p',
        ]);
        $parser->addOption('logger', [
            'help' => 'Name of a configured logger',
            'default' => 'stdout',
            'short' => 'l',
        ]);
        $parser->addOption('max-iterations', [
            'help' => 'Number of max iterations to run',
            'default' => null,
            'short' => 'i',
        ]);
        $parser->addOption('max-runtime', [
            'help' => 'Seconds for max runtime',
            'default' => null,
            'short' => 'r',
        ]);
        $parser->setDescription(
            'Runs a queue worker that consumes from the named queue.'
        );

        return $parser;
    }

    /**
     * Creates and returns a QueueExtension object
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \Cake\Queue\Consumption\QueueExtension
     */
    protected function getQueueExtension(Arguments $args, LoggerInterface $logger): QueueExtension
    {
        $maxIterations = (int)$args->getOption('max-iterations');
        $maxRuntime = (int)$args->getOption('max-runtime');

        return new QueueExtension($maxIterations, $maxRuntime, $logger);
    }

    /**
     * Creates and returns a LoggerInterface object
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger(Arguments $args): LoggerInterface
    {
        $logger = null;
        if (!empty($args->getOption('verbose'))) {
            $logger = Log::engine((string)$args->getOption('logger'));
        }

        return $logger ?? new NullLogger();
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return int|void|null
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $logger = $this->getLogger($args);
        $processor = new Processor($logger);
        $extension = $this->getQueueExtension($args, $logger);

        $config = (string)$args->getOption('config');
        if (!Configure::check(sprintf('Queue.%s', $config))) {
            $io->error(sprintf('Configuration key "%s" was not found', $config));
            $this->abort();
        }

        $hasListener = Configure::check(sprintf('Queue.%s.listener', $config));
        if ($hasListener) {
            $listenerClassName = Configure::read(sprintf('Queue.%s.listener', $config));
            if (!class_exists($listenerClassName)) {
                $io->error(sprintf('Listener class %s not found', $listenerClassName));
                $this->abort();
            }

            /** @var \Cake\Event\EventListenerInterface $listener */
            $listener = new $listenerClassName();
            $processor->getEventManager()->on($listener);
            $extension->getEventManager()->on($listener);
        }
        $config = Configure::read(sprintf('Queue.%s', $config));

        $simpleClientConfig = new SimpleClientConfig((string)$args->getOption('queue'), $config);

        $client = new SimpleClient($simpleClientConfig->getConfig(), $logger);
        
        /** @psalm-suppress InvalidArgument */
        $client->bindTopic($simpleClientConfig->getQueue(), $processor, $args->getOption('processor'));
        $client->consume($extension);
    }
}
