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
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Container\ContainerInterface;
use Cake\Core\Configure;
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
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Worker command.
 */
class WorkerCommand extends Command
{
    /**
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory.
     * @param \Cake\Container\ContainerInterface|null $container DI container instance
     */
    public function __construct(
        ?CommandFactoryInterface $factory = null,
        protected readonly ?ContainerInterface $container = null,
    ) {
        parent::__construct($factory);
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
            'Runs a queue worker that consumes from the named queue.',
        );

        return $parser;
    }

    /**
     * Creates and returns a QueueExtension object
     *
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \Enqueue\Consumption\ExtensionInterface
     */
    protected function getQueueExtension(LoggerInterface $logger): ExtensionInterface
    {
        $limitAttempsExtension = new LimitAttemptsExtension((int)$this->args->getOption('max-attempts') ?: null);

        $limitAttempsExtension->getEventManager()->on(new FailedJobsListener());

        $configKey = (string)$this->args->getOption('config');
        $config = QueueManager::getConfig($configKey);

        $extensions = [
            new LoggerExtension($logger),
            $limitAttempsExtension,
        ];

        if ($this->args->getOption('max-jobs') !== null) {
            $maxJobs = (int)$this->args->getOption('max-jobs');
            $extensions[] = new LimitConsumedMessagesExtension($maxJobs);
        }

        if ($this->args->getOption('max-runtime') !== null) {
            $endTime = new DateTime(sprintf('+%d seconds', (int)$this->args->getOption('max-runtime')));
            $extensions[] = new LimitConsumptionTimeExtension($endTime);
        }

        if (isset($config['uniqueCacheKey'])) {
            $extensions[] = new RemoveUniqueJobIdFromCacheExtension($config['uniqueCacheKey']);
        }

        return new ChainExtension($extensions);
    }

    /**
     * Creates and returns a LoggerInterface object
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        $logger = null;
        if (!(in_array($this->args->getOption('verbose'), ['', '0'], true) || $this->args->getOption('verbose') === false || $this->args->getOption('verbose') === null)) {
            $logger = Log::engine((string)$this->args->getOption('logger'));
        }

        return $logger ?? new NullLogger();
    }

    /**
     * Creates and returns a Processor object
     *
     * @param \Psr\Log\LoggerInterface $logger Logger instance
     * @return \Interop\Queue\Processor
     */
    protected function getProcessor(LoggerInterface $logger): InteropProcessor
    {
        $configKey = (string)$this->args->getOption('config');
        $config = QueueManager::getConfig($configKey);

        $processorClass = $config['processor'] ?? Processor::class;

        if (!class_exists($processorClass)) {
            $this->io->error(sprintf('Processor class %s not found', $processorClass));
            $this->abort();
        }

        if (!is_subclass_of($processorClass, InteropProcessor::class)) {
            $this->io->error(sprintf('Processor class %s must implement Interop\Queue\Processor', $processorClass));
            $this->abort();
        }

        return new $processorClass($logger, $this->container);
    }

    /**
     * @return int
     */
    public function execute(): int
    {
        $config = (string)$this->args->getOption('config');
        if (!Configure::check(sprintf('Queue.%s', $config))) {
            $this->io->error(sprintf('Configuration key "%s" was not found', $config));
            $this->abort();
        }

        $logger = $this->getLogger();
        $processor = $this->getProcessor($logger);
        $extension = $this->getQueueExtension($logger);
        $hasListener = Configure::check(sprintf('Queue.%s.listener', $config));
        if ($hasListener) {
            $listenerClassName = Configure::read(sprintf('Queue.%s.listener', $config));
            if (!class_exists($listenerClassName)) {
                $this->io->error(sprintf('Listener class %s not found', $listenerClassName));
                $this->abort();
            }

            /** @var \Cake\Event\EventListenerInterface $listener */
            $listener = new $listenerClassName();

            if ($processor instanceof Processor) {
                $processor->getEventManager()->on($listener);
            }
        }

        $client = QueueManager::engine($config);
        $queue = $this->args->getOption('queue')
            ? (string)$this->args->getOption('queue')
            : Configure::read(sprintf('Queue.%s.queue', $config), 'default');
        $processorName = $this->args->getOption('processor') ? (string)$this->args->getOption('processor') : 'default';
        $client->bindTopic($queue, $processor, $processorName);
        $client->consume($extension);

        return self::CODE_SUCCESS;
    }
}
