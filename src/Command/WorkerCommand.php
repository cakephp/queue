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
use Cake\Core\ContainerInterface;
use Cake\Log\Log;
use Cake\Queue\Consumption\LimitAttemptsExtension;
use Cake\Queue\Consumption\LimitConsumedMessagesExtension;
use Cake\Queue\Consumption\RemoveUniqueJobIdFromCacheExtension;
use Cake\Queue\Listener\FailedJobsListener;
use Cake\Queue\Queue\Processor;
use Cake\Queue\QueueManager;
use DateTime;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\LimitConsumptionTimeExtension;
use Enqueue\Consumption\Extension\LoggerExtension;
use Enqueue\Consumption\ExtensionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Worker command.
 */
class WorkerCommand extends Command
{
    /**
     * @var \Cake\Core\ContainerInterface|null
     */
    protected $container;

    /**
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(?ContainerInterface $container = null)
    {
        parent::__construct();
        $this->container = $container;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'queue worker';
    }

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
            'help' => 'Name of queue to bind to. Defaults to the queue config (--config).',
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
        $parser->addOption('max-jobs', [
            'help' => 'Maximum number of jobs to process. Worker will exit after limit is reached.',
            'default' => null,
            'short' => 'i',
        ]);
        $parser->addOption('max-runtime', [
            'help' => 'Maximum number of seconds worker will run. Worker will exit after limit is reached.',
            'default' => null,
            'short' => 'r',
        ]);
        $parser->addOption('max-attempts', [
            'help' => 'Maximum number of times each job will be attempted.'
                . ' Maximum attempts defined on a job will override this value.',
            'default' => null,
            'short' => 'a',
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
     * @return \Enqueue\Consumption\ExtensionInterface
     */
    protected function getQueueExtension(Arguments $args, LoggerInterface $logger): ExtensionInterface
    {
        $limitAttempsExtension = new LimitAttemptsExtension((int)$args->getOption('max-attempts') ?: null);

        $limitAttempsExtension->getEventManager()->on(new FailedJobsListener());

        $extensions = [
            new LoggerExtension($logger),
            $limitAttempsExtension,
            new RemoveUniqueJobIdFromCacheExtension('Cake/Queue.queueUnique'),
        ];

        if (!is_null($args->getOption('max-jobs'))) {
            $maxJobs = (int)$args->getOption('max-jobs');
            $extensions[] = new LimitConsumedMessagesExtension($maxJobs);
        }

        if (!is_null($args->getOption('max-runtime'))) {
            $endTime = new DateTime(sprintf('+%d seconds', (int)$args->getOption('max-runtime')));
            $extensions[] = new LimitConsumptionTimeExtension($endTime);
        }

        return new ChainExtension($extensions);
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
        $processor = new Processor($logger, $this->container);
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
        }
        $client = QueueManager::engine($config);
        $queue = $args->getOption('queue')
            ? (string)$args->getOption('queue')
            : Configure::read("Queue.{$config}.queue", 'default');
        $processorName = $args->getOption('processor') ? (string)$args->getOption('processor') : null;

        $client->bindTopic($queue, $processor, $processorName);
        $client->consume($extension);
    }
}
