<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

class RecordingMiddleware implements BusMiddlewareInterface
{
    /**
     * @param array<string> $calls
     */
    public function __construct(
        private readonly string $name,
        private array &$calls,
    ) {
    }

    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $this->calls[] = $this->name . '.enter';
        $finished = $stack->next()->handle($envelope, $stack);
        $this->calls[] = $this->name . '.exit';

        return $finished;
    }

    /**
     * Get the recorded calls.
     *
     * @return array<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
