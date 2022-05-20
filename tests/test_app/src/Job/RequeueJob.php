<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Log\Log;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class RequeueJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        Log::debug('RequeueJob is requeueing');

        return Processor::REQUEUE;
    }
}
