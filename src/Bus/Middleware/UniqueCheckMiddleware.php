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
namespace Cake\Queue\Bus\Middleware;

use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use Cake\Queue\Bus\StampInterface;
use Cake\Queue\QueueManager;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Drops duplicate dispatches of unique jobs before sending.
 *
 * Owns the check-then-act block formerly inline in `QueueManager::push()`,
 * with identical semantics (including the check-then-act shape; hardening
 * the race is a separate follow-up). The unique identity is always the
 * `QueueManager::getUniqueId()` hash computed from the dispatch.
 *
 * Short-circuits the pipeline on duplicates: downstream middleware never
 * runs and no `SentStamp` is attached. A `SkippedAsDuplicateStamp` marks the
 * envelope instead, so the dispatch result resolves to `null` for duplicates
 * only. The cache entry is added only after the downstream pipeline
 * succeeds, mirroring the former read-before-send / add-after-send order.
 */
final class UniqueCheckMiddleware implements BusMiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $dispatch = $envelope->getMessage();
        if (!$dispatch instanceof Dispatch) {
            throw new RuntimeException('UniqueCheckMiddleware requires a Dispatch message.');
        }

        $class = $dispatch->getClass();
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Dispatch class `%s` does not exist.', $class));
        }

        if (empty($class::$shouldBeUnique)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $config = QueueManager::getConfig($dispatch->getConfigName()) ?? [];
        if (empty($config['uniqueCache'])) {
            throw new InvalidArgumentException(
                $class . '::$shouldBeUnique is set to `true` but `uniqueCache` configuration is missing.',
            );
        }

        $uniqueId = QueueManager::getUniqueId(
            $class,
            $dispatch->getMethod(),
            $dispatch->getData(),
            $dispatch->getDtoClass(),
        );

        if (Cache::read($uniqueId, $config['uniqueCacheKey'])) {
            $logger = !empty($config['logger']) ? Log::engine($config['logger']) : null;
            if ($logger instanceof LoggerInterface) {
                $logger->debug(
                    sprintf(
                        'An identical instance of %s already exists on the queue. This push will be ignored.',
                        $class,
                    ),
                );
            }

            return $envelope->withStamp(new SkippedAsDuplicateStamp());
        }

        $finished = $stack->next()->handle($envelope, $stack);

        if ($finished->getLastStamp(SentStamp::class) instanceof StampInterface) {
            Cache::add($uniqueId, true, $config['uniqueCacheKey']);
        }

        return $finished;
    }
}
