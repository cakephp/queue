<?php
namespace Queue\Job;

use Cake\Event\EventManager;
use Interop\Queue\Processor;
use Queue\Job\JobInterface;
use Queue\Job\Message;

class EventJob implements JobInterface
{
    /**
     * Constructs and dispatches the event from a job message
     *
     * @param Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string
    {
        $eventClass = $message->getArgument('className');
        $eventName = $message->getArgument('eventName');
        $data = $message->getArgument('data', []);
        if (!class_exists($eventClass)) {
          return Processor::REJECT;
        }

        $event = new $eventClass($eventName, null, $data);
        EventManager::instance()->dispatch($event);
        if ($event->isStopped()) {
            return Processor::REJECT;
        }

        if (!empty($event->getResult()['return'])) {
            return $event->getResult()['return'];
        }

        return Processor::ACK;
    }
}
