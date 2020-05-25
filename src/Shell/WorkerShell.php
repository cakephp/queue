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
namespace Cake\Queue\Shell;

use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Queue\Consumption\QueueExtension;
use Cake\Queue\Queue\Processor;
use Enqueue\SimpleClient\SimpleClient;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Worker shell command.
 */
class WorkerShell extends Shell
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
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \Cake\Queue\Consumption\QueueExtension
     */
    protected function getQueueExtension(LoggerInterface $logger): QueueExtension
    {
        $maxIterations = $this->param('max-iterations');
        $maxRuntime = $this->param('max-runtime');
        if ($maxIterations === null) {
            $maxIterations = 0;
        }

        if ($maxRuntime === null) {
            $maxRuntime = 0;
        }

        return new QueueExtension($maxIterations, $maxRuntime, $logger);
    }

    /**
     * Creates and returns a LoggerInterface object
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        $logger = new NullLogger();
        if (!empty($this->params['verbose'])) {
            $logger = Log::engine($this->params['logger']);
        }

        return $logger;
    }

    /**
     * main() method.
     *
     * @return null
     */
    public function main()
    {
        $logger = $this->getLogger();
        $processor = new Processor($logger);
        $extension = $this->getQueueExtension($logger);

        $config = $this->params['config'];
        if (!empty($config['listener'])) {
            if (!class_exists($config['listener'])) {
                throw new LogicException(sprintf('Listener class %s not found', $config['listener']));
            }

            $listener = new $config['listener']();
            $processor->getEventManager()->on($listener);
            $extension->getEventManager()->on($listener);
        }

        $url = Configure::read(sprintf('Queue.%s.url', $config));
        $client = new SimpleClient($url, $logger);
        $client->bindTopic($this->params['queue'], $processor);
        $client->consume($extension);

        return null;
    }
}
