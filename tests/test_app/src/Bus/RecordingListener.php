<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;

class RecordingListener implements EventListenerInterface
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function implementedEvents(): array
    {
        return [
            'Processor.message.seen' => 'record',
            'Processor.message.start' => 'record',
            'Processor.message.success' => 'record',
            'Processor.message.reject' => 'record',
            'Processor.message.failure' => 'record',
            'Processor.message.invalid' => 'record',
            'Processor.message.exception' => 'record',
        ];
    }

    /**
     * @param \Cake\Event\EventInterface $event Event.
     * @return void
     */
    public function record(EventInterface $event): void
    {
        $this->events[] = $event->getName();
    }
}
