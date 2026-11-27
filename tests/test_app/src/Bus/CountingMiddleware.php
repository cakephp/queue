<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

class CountingMiddleware implements BusMiddlewareInterface
{
    public static int $constructed = 0;

    public int $calls = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $this->calls++;

        return $stack->next()->handle($envelope, $stack);
    }
}
