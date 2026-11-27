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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Bus;

use Cake\Core\ContainerInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManagerInterface;
use Cake\Queue\Bus\Middleware\HandlerMiddleware;
use Cake\Queue\Bus\Middleware\ProcessorAdapterMiddleware;
use Cake\Queue\Bus\Middleware\SendMiddleware;
use Cake\Queue\Bus\Middleware\UniqueCheckMiddleware;
use Cake\Queue\Consumption\LimitAttemptsExtension;
use Cake\Queue\Consumption\LimitConsumedMessagesExtension;
use Cake\Queue\Consumption\RemoveUniqueJobIdFromCacheExtension;
use Cake\Queue\Listener\FailedJobsListener;
use Cake\Queue\QueueManager;
use Closure;
use DateTime;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\LimitConsumptionTimeExtension;
use Enqueue\Consumption\Extension\LoggerExtension;
use Enqueue\Consumption\ExtensionInterface;
use Interop\Queue\Processor as InteropProcessor;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Named assembly for the dispatch bus, the consume bus and the transport
 * consumption extensions.
 *
 * Both buses are rebuilt on every call. Class names and factory closures
 * yield fresh middleware per call (unless the container returns a shared
 * service); prebuilt instances from config are reused as-is across calls,
 * which enables cross-message state such as rate limiting or circuit
 * breaking. Keep middleware either stateless or deliberately shared:
 * per-message state in the properties of a shared instance leaks across
 * messages. The transport send itself dominates the cost of a fresh
 * assembly. `reset()` is kept as a no-op for backward compatibility and
 * `QueueManager` still calls it on config drops and container swaps.
 */
class BusFactory
{
    /**
     * Queue config key holding the dispatch-side user middleware list.
     */
    public const DISPATCH_MIDDLEWARE = 'dispatchMiddleware';

    /**
     * Queue config key holding the consume-side user middleware list.
     */
    public const CONSUME_MIDDLEWARE = 'consumeMiddleware';

    /**
     * Drop cached buses.
     *
     * Kept for backward compatibility; buses are rebuilt on every call and
     * nothing is cached. Callers can keep calling it on config drops and
     * container swaps.
     *
     * @param string|null $configName Config name to drop, or null for all buses.
     * @return void
     */
    public static function reset(?string $configName = null): void
    {
    }

    /**
     * Get the app-defined stamp types for a queue config.
     *
     * Reads the `Queue.<name>.stampTypes` map (short `type` string to
     * `class-string<StampInterface>`) and merges it over the core types.
     * Used for parsing transport bodies; writers need no registry because
     * every stamp serializes itself through `toArray()`.
     *
     * @param string $configName Queue config name.
     * @return array<string, class-string<\Cake\Queue\Bus\StampInterface>>
     */
    public static function stampTypes(string $configName): array
    {
        $config = QueueManager::getConfig($configName) ?? [];
        $customTypes = $config['stampTypes'] ?? [];
        if (!is_array($customTypes)) {
            throw new InvalidArgumentException(sprintf(
                'Queue config `%s.stampTypes` must be an array of type name to stamp class.',
                $configName,
            ));
        }

        return Envelope::resolveStampTypes($customTypes);
    }

    /**
     * Get the dispatch bus for a queue config.
     *
     * Pipeline: user `dispatchMiddleware` from config, `UniqueCheckMiddleware`,
     * terminal `SendMiddleware`. A fresh bus is built on every call, but only
     * class names and factory closures resolve to new instances: prebuilt
     * middleware instances are reused as is, so keep them stateless.
     *
     * @param string $configName Queue config name.
     * @return \Cake\Queue\Bus\MessageBus
     */
    public static function dispatchBus(string $configName): MessageBus
    {
        return new MessageBus([
            ...static::userMiddleware($configName, static::DISPATCH_MIDDLEWARE),
            new UniqueCheckMiddleware(),
            new SendMiddleware(),
        ]);
    }

    /**
     * Get the consume bus for a queue config.
     *
     * Pipeline: user `consumeMiddleware` from config, terminal `HandlerMiddleware`
     * (or a transparent adapter around a custom `$config['processor']`, which
     * keeps working exactly as without the bus). Transport mechanics stay
     * outside the bus as the `ChainExtension` from `consumeExtensions()`. The
     * `Queue.listener` is attached to the terminal on the default path and
     * to the custom processor itself when it exposes an event manager
     * (as before the bus); processors without one stay silent, also as before.
     *
     * A fresh bus is built on every call because the terminal depends on the
     * per-call options below; never cache or reuse the result across workers
     * with different options.
     *
     * @param string $configName Queue config name.
     * @param array{logger?: \Psr\Log\LoggerInterface, processor?: \Interop\Queue\Processor|null, listener?: \Cake\Event\EventListenerInterface|null} $options Bus options.
     * @return \Cake\Queue\Bus\MessageBus
     */
    public static function consumeBus(string $configName, array $options = []): MessageBus
    {
        $processor = $options['processor'] ?? null;
        $listener = $options['listener'] ?? null;
        $logger = $options['logger'] ?? null;
        if ($processor !== null && !$processor instanceof InteropProcessor) {
            throw new InvalidArgumentException(sprintf(
                'Consume bus processor must implement `%s`, `%s` given.',
                InteropProcessor::class,
                get_debug_type($processor),
            ));
        }

        if ($listener !== null && !$listener instanceof EventListenerInterface) {
            throw new InvalidArgumentException(sprintf(
                'Consume bus listener must implement `%s`, `%s` given.',
                EventListenerInterface::class,
                get_debug_type($listener),
            ));
        }

        if ($logger !== null && !$logger instanceof LoggerInterface) {
            throw new InvalidArgumentException(sprintf(
                'Consume bus logger must implement `%s`, `%s` given.',
                LoggerInterface::class,
                get_debug_type($logger),
            ));
        }

        if (!$processor instanceof InteropProcessor) {
            $terminal = new HandlerMiddleware($logger ?? new NullLogger());
            if ($listener instanceof EventListenerInterface) {
                $terminal->getEventManager()->on($listener);
            }
        } else {
            $terminal = new ProcessorAdapterMiddleware($processor);
            if ($listener instanceof EventListenerInterface && method_exists($processor, 'getEventManager')) {
                $events = $processor->getEventManager();
                if ($events instanceof EventManagerInterface) {
                    $events->on($listener);
                }
            }
        }

        return new MessageBus([
            ...static::userMiddleware($configName, static::CONSUME_MIDDLEWARE),
            $terminal,
        ]);
    }

    /**
     * Build the transport consumption extensions for a worker.
     *
     * The assembly moved here verbatim from `WorkerCommand::getQueueExtension()`:
     * logger, attempt limiting with failed-job recording, optional consumption
     * limits and unique-job cache release.
     *
     * @param string $configName Queue config name.
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @param int|null $maxAttempts Maximum number of times each job will be attempted.
     * @param int|null $maxJobs Maximum number of jobs to consume.
     * @param int|null $maxRuntime Maximum number of seconds the worker will run.
     * @return \Enqueue\Consumption\ExtensionInterface
     */
    public static function consumeExtensions(
        string $configName,
        LoggerInterface $logger,
        ?int $maxAttempts = null,
        ?int $maxJobs = null,
        ?int $maxRuntime = null,
    ): ExtensionInterface {
        if ($maxAttempts !== null && $maxAttempts < 0) {
            throw new InvalidArgumentException('Consume `maxAttempts` must be null or a non-negative integer.');
        }

        if ($maxJobs !== null && $maxJobs < 0) {
            throw new InvalidArgumentException('Consume `maxJobs` must be null or a non-negative integer.');
        }

        if ($maxRuntime !== null && $maxRuntime < 0) {
            throw new InvalidArgumentException('Consume `maxRuntime` must be null or a non-negative integer.');
        }

        $limitAttemptsExtension = new LimitAttemptsExtension($maxAttempts);

        $limitAttemptsExtension->getEventManager()->on(new FailedJobsListener());

        $config = QueueManager::getConfig($configName);

        $extensions = [
            new LoggerExtension($logger),
            $limitAttemptsExtension,
        ];

        if ($maxJobs !== null) {
            $extensions[] = new LimitConsumedMessagesExtension($maxJobs);
        }

        if ($maxRuntime !== null) {
            $endTime = new DateTime(sprintf('+%d seconds', $maxRuntime));
            $extensions[] = new LimitConsumptionTimeExtension($endTime);
        }

        if (isset($config['uniqueCacheKey'])) {
            $extensions[] = new RemoveUniqueJobIdFromCacheExtension($config['uniqueCacheKey']);
        }

        return new ChainExtension($extensions);
    }

    /**
     * Resolve user middleware declared in queue config.
     *
     * Accepts prebuilt instances, class names and factory closures (invoked
     * with the container, which may be null). A closure may also return a
     * class name, resolved the same way. Class names resolve from the
     * container when registered there, exactly like jobs, and are
     * instantiated directly otherwise. Classes needing constructor arguments
     * must be registered in the container or wrapped in a factory closure.
     *
     * @param string $configName Queue config name.
     * @param string $key Config key holding the middleware list.
     * @return list<\Cake\Queue\Bus\BusMiddlewareInterface>
     */
    protected static function userMiddleware(string $configName, string $key): array
    {
        $config = QueueManager::getConfig($configName) ?? [];
        $list = $config[$key] ?? [];
        if (!is_array($list)) {
            throw new InvalidArgumentException(
                sprintf('Queue config `%s.%s` must be a list of bus middleware.', $configName, $key),
            );
        }

        $global = QueueManager::getContainer();
        $container = $global instanceof ContainerInterface ? $global : null;

        $resolved = [];
        foreach ($list as $middleware) {
            if ($middleware instanceof Closure) {
                $middleware = $middleware($container);
            }

            if (is_string($middleware)) {
                if ($container instanceof ContainerInterface && $container->has($middleware)) {
                    try {
                        $middleware = $container->get($middleware);
                    } catch (Throwable $containerError) {
                        throw new InvalidArgumentException(
                            sprintf(
                                'Bus middleware `%s` could not be resolved: %s',
                                $middleware,
                                $containerError->getMessage(),
                            ),
                            (int)$containerError->getCode(),
                            $containerError,
                        );
                    }
                } else {
                    if (!class_exists($middleware)) {
                        throw new InvalidArgumentException(sprintf(
                            'Bus middleware `%s` could not be resolved.',
                            $middleware,
                        ));
                    }

                    $middleware = static::resolveMiddleware($middleware, $container);
                }
            }

            if (!$middleware instanceof BusMiddlewareInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Bus middleware must implement `%s`, `%s` given.',
                        BusMiddlewareInterface::class,
                        get_debug_type($middleware),
                    ),
                );
            }

            $resolved[] = $middleware;
        }

        return $resolved;
    }

    /**
     * Resolve one middleware class name.
     *
     * Same rule as jobs (`Message::getCallable()`): registered services come
     * from the container, everything else is instantiated directly.
     *
     * @param class-string $class Middleware class name.
     * @param \Cake\Core\ContainerInterface|null $container Container instance or null.
     * @return mixed Resolved middleware instance.
     */
    protected static function resolveMiddleware(string $class, ?ContainerInterface $container): mixed
    {
        if ($container instanceof ContainerInterface && $container->has($class)) {
            return $container->get($class);
        }

        if (!static::canInstantiateWithoutArguments($class)) {
            throw new InvalidArgumentException(sprintf(
                'Bus middleware `%s` could not be resolved: register it in the container or use a factory closure.',
                $class,
            ));
        }

        return new $class();
    }

    /**
     * Check whether a middleware class can be built without constructor arguments.
     *
     * Anything else (non-instantiable classes, non-public constructors, or
     * constructors with required parameters) must come from the container or
     * a factory closure, so the failure surfaces as an
     * `InvalidArgumentException` instead of a raw engine error from `new`.
     *
     * @param class-string $class Middleware class name.
     */
    public static function canInstantiateWithoutArguments(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            return false;
        }

        $constructor = $reflection->getConstructor();

        return !$constructor instanceof ReflectionMethod
            || ($constructor->isPublic() && $constructor->getNumberOfRequiredParameters() === 0);
    }
}
