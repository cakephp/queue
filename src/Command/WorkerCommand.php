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
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Cake\Queue\Bus\BusFactory;
use Cake\Queue\Bus\BusProcessor;
use Cake\Queue\QueueManager;
use Enqueue\Consumption\ExtensionInterface;
use Interop\Queue\Processor as InteropProcessor;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Worker command.
 */
class WorkerCommand extends Command
{
    /**
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(
        protected readonly ?ContainerInterface $container = null,
    ) {
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
     * The assembly lives in `BusFactory::consumeExtensions()`; this method
     * maps console options onto it so subclasses overriding it keep working.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \Enqueue\Consumption\ExtensionInterface
     */
    protected function getQueueExtension(Arguments $args, LoggerInterface $logger): ExtensionInterface
    {
        return BusFactory::consumeExtensions(
            (string)$args->getOption('config'),
            $logger,
            (int)$args->getOption('max-attempts') ?: null,
            ($maxJobs = $args->getOption('max-jobs')) !== null ? (int)$maxJobs : null,
            ($maxRuntime = $args->getOption('max-runtime')) !== null ? (int)$maxRuntime : null,
        );
    }

    /**
     * Creates and returns a LoggerInterface object
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger(Arguments $args): LoggerInterface
    {
        $verbose = $args->getOption('verbose');
        $quiet = in_array($verbose, ['', '0'], true) || $verbose === false || $verbose === null;
        if (!$quiet) {
            return Log::engine((string)$args->getOption('logger')) ?? new NullLogger();
        }

        return new NullLogger();
    }

    /**
     * Creates and returns a Processor object
     *
     * Always returns an `Interop\Queue\Processor` ready to bind to the
     * transport. Without a custom `processor` config this is a bus-backed
     * `BusProcessor` (consume bus with `HandlerMiddleware` as the terminal);
     * with one, the configured class resolved from the container when
     * registered there, instantiated directly otherwise.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @param \Psr\Log\LoggerInterface $logger Logger instance
     * @return \Interop\Queue\Processor
     */
    protected function getProcessor(Arguments $args, ConsoleIo $io, LoggerInterface $logger): InteropProcessor
    {
        $configKey = (string)$args->getOption('config');
        $config = QueueManager::getConfig($configKey) ?? [];

        $processorClass = $config['processor'] ?? null;
        if ($processorClass === null) {
            $bus = BusFactory::consumeBus($configKey, [
                'logger' => $logger,
                'listener' => $this->getListener($args, $io),
            ]);

            return new BusProcessor($bus, $this->container, $configKey, BusFactory::stampTypes($configKey), $logger);
        }

        if (!is_string($processorClass) || !class_exists($processorClass)) {
            $io->error(sprintf(
                'Processor class %s not found',
                is_string($processorClass) ? $processorClass : get_debug_type($processorClass),
            ));
            $this->abort();
        }

        if (!is_subclass_of($processorClass, InteropProcessor::class)) {
            $io->error(sprintf('Processor class %s must implement Interop\Queue\Processor', $processorClass));
            $this->abort();
        }

        if ($this->container instanceof ContainerInterface && $this->container->has($processorClass)) {
            try {
                $processor = $this->container->get($processorClass);
            } catch (Throwable $processorError) {
                $io->error(sprintf(
                    'Processor class %s could not be resolved: %s',
                    $processorClass,
                    $processorError->getMessage(),
                ));
                $this->abort();
            }

            if (!$processor instanceof InteropProcessor) {
                $io->error(sprintf('Processor class %s must implement Interop\Queue\Processor', $processorClass));
                $this->abort();
            }

            return $processor;
        }

        $reflection = new ReflectionClass($processorClass);
        $constructor = $reflection->getConstructor();
        if (!$reflection->isInstantiable() || ($constructor instanceof ReflectionMethod && !$constructor->isPublic())) {
            $io->error(sprintf(
                'Processor class %s could not be resolved: register it in the container.',
                $processorClass,
            ));
            $this->abort();
        }

        try {
            return $this->newProcessorInstance($processorClass, $logger);
        } catch (Throwable $throwable) {
            $io->error(sprintf(
                'Processor class %s could not be resolved: %s',
                $processorClass,
                $throwable->getMessage(),
            ));
            $this->abort();
        }
    }

    /**
     * Instantiate a custom processor with the worker logger and container.
     *
     * Constructors can fail with any throwable (missing arguments, type
     * mismatches, wiring failures in the body); the caller converts that
     * into a clean abort. The `@throws` documents the throw point that
     * `new` on a class-string does not expose to static analysis.
     *
     * @param class-string<\Interop\Queue\Processor> $class Processor class name.
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \Interop\Queue\Processor
     * @throws \Throwable When instantiation fails.
     */
    protected function newProcessorInstance(string $class, LoggerInterface $logger): InteropProcessor
    {
        return new $class($logger, $this->container);
    }

    /**
     * Creates and returns a listener object
     *
     * Resolves the `Queue.<config>.listener` setting the same way jobs are
     * built: a prebuilt instance is used as is, a class name is taken from
     * the container when registered there and instantiated directly
     * otherwise. Returns null when no listener is configured.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     */
    protected function getListener(Arguments $args, ConsoleIo $io): ?EventListenerInterface
    {
        $config = (string)$args->getOption('config');
        if (!Configure::check(sprintf('Queue.%s.listener', $config))) {
            return null;
        }

        $listener = Configure::read(sprintf('Queue.%s.listener', $config));
        if ($listener instanceof EventListenerInterface) {
            return $listener;
        }

        $listenerClass = $listener;
        if (!is_string($listenerClass) || !class_exists($listenerClass)) {
            $io->error(sprintf(
                'Listener class %s not found',
                is_string($listenerClass) ? $listenerClass : get_debug_type($listenerClass),
            ));
            $this->abort();
        }

        if (!is_subclass_of($listenerClass, EventListenerInterface::class)) {
            $io->error(sprintf(
                'Listener class %s must implement Cake\Event\EventListenerInterface',
                $listenerClass,
            ));
            $this->abort();
        }

        $container = $this->container;
        if ($container instanceof ContainerInterface && $container->has($listenerClass)) {
            try {
                $listener = $container->get($listenerClass);
            } catch (Throwable $listenerError) {
                $io->error(sprintf(
                    'Listener class %s could not be resolved: %s',
                    $listenerClass,
                    $listenerError->getMessage(),
                ));
                $this->abort();
            }
        } else {
            if (!BusFactory::canInstantiateWithoutArguments($listenerClass)) {
                $io->error(sprintf(
                    'Listener class %s could not be resolved: register it in the container.',
                    $listenerClass,
                ));
                $this->abort();
            }

            $listener = new $listenerClass();
        }

        if (!$listener instanceof EventListenerInterface) {
            $io->error(sprintf(
                'Listener class %s must implement Cake\Event\EventListenerInterface',
                $listenerClass,
            ));
            $this->abort();
        }

        return $listener;
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $config = (string)$args->getOption('config');
        if (!Configure::check(sprintf('Queue.%s', $config))) {
            $io->error(sprintf('Configuration key "%s" was not found', $config));
            $this->abort();
        }

        $logger = $this->getLogger($args);
        $processor = $this->getProcessor($args, $io, $logger);
        try {
            $extension = $this->getQueueExtension($args, $logger);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $io->error($invalidArgumentException->getMessage());
            $this->abort();
        }

        if (!$processor instanceof BusProcessor) {
            $bus = BusFactory::consumeBus($config, [
                'logger' => $logger,
                'processor' => $processor,
                'listener' => $this->getListener($args, $io),
            ]);
            $processor = new BusProcessor($bus, $this->container, $config, BusFactory::stampTypes($config), $logger);
        }

        $client = QueueManager::engine($config, $this->container);
        $queue = $args->getOption('queue')
            ? (string)$args->getOption('queue')
            : Configure::read(sprintf('Queue.%s.queue', $config), 'default');
        $processorName = $args->getOption('processor') ? (string)$args->getOption('processor') : 'default';

        $client->bindTopic(
            $queue,
            $processor,
            $processorName,
        );
        $client->consume($extension);

        return self::CODE_SUCCESS;
    }
}
