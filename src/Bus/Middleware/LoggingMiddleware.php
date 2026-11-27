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

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\PhaseEnum;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reference user middleware: logs the envelope phase and payload type.
 *
 * Needs no stamps of its own and works on both buses: on the dispatch bus
 * the envelope has no `ReceivedStamp` yet, on the consume bus it does.
 * Copy this shape for app middleware (tenant scoping, metrics, envelope
 * validation) with app-defined stamps registered via `stampTypes`.
 */
final class LoggingMiddleware implements BusMiddlewareInterface
{
    protected readonly LoggerInterface $logger;

    /**
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @inheritDoc
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $phase = PhaseEnum::fromEnvelope($envelope);
        $this->logger->debug(sprintf(
            'Bus envelope with `%s` message entering %s phase.',
            $envelope->getMessage()::class,
            $phase->value,
        ));

        return $stack->next()->handle($envelope, $stack);
    }
}
