<?php
namespace Queue\Job;

use Cake\Event\EventManager;
use Queue\Job\JobInterface;
use Queue\Queue\JobData;
use Interop\Queue\Processor;

class EventJob implements JobInterface
{
    /**
     * Constructs and dispatches the event from a job data bag
     *
     * @param JobData $jobData job data bag
     * @return string
     */
    public function execute(JobData $jobData): ?string
    {
        $eventClass = $jobData->getArgument('className');
        $eventName = $jobData->getArgument('eventName');
        $data = $jobData->getArgument('data', []);
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
