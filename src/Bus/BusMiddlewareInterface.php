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

/**
 * Single pipeline stage operating on an envelope.
 *
 * A middleware either forwards to the next stage, short-circuits by
 * returning an envelope without calling the stack, or replaces the
 * envelope (extra stamps, replaced message) before forwarding.
 *
 * Short-circuiting consume without a `HandledStamp` resolves to `REQUEUE`
 * (bounded by the worker retry limits): to drop a message intentionally,
 * end the pipeline with `$envelope->withStamp(new HandledStamp(ACK))`
 * instead of returning the envelope untouched.
 */
interface BusMiddlewareInterface
{
    /**
     * Handle the envelope.
     *
     * @param \Cake\Queue\Bus\Envelope $envelope Envelope to handle.
     * @param \Cake\Queue\Bus\BusStackInterface $stack Stack to resolve the next middleware.
     * @return \Cake\Queue\Bus\Envelope
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope;
}
