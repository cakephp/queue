<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

/**
 * Middleware with a private constructor: unresolvable without the container.
 */
class PrivateConstructorMiddleware implements BusMiddlewareInterface
{
    private function __construct()
    {
    }

    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope, $stack);
    }
}
