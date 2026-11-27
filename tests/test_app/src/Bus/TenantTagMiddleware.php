<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

/**
 * App-level middleware used to test per-queue user middleware.
 *
 * Tags every envelope with a fixed tenant stamp. Declared with a no-argument
 * constructor so it can be referenced by class name from queue config.
 */
class TenantTagMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope->withStamp(new TenantStamp('tenant:test')), $stack);
    }
}
