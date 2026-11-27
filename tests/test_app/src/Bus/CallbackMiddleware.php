<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

class CallbackMiddleware implements BusMiddlewareInterface
{
    /**
     * @var callable(\Cake\Queue\Bus\Envelope, \Cake\Queue\Bus\BusStackInterface): \Cake\Queue\Bus\Envelope
     */
    private $callback;

    /**
     * @param callable(\Cake\Queue\Bus\Envelope, \Cake\Queue\Bus\BusStackInterface): \Cake\Queue\Bus\Envelope $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        return ($this->callback)($envelope, $stack);
    }
}
