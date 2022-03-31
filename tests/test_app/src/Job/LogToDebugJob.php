<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Log\Log;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class LogToDebugJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        Log::debug('Debug job was run');

        return Processor::ACK;
    }
}
