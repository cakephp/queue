<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Log\Log;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class UniqueJob implements JobInterface
{
    public static $shouldBeUnique = true;

    public function execute(Message $message): ?string
    {
        Log::debug('Unique job was run');

        return Processor::ACK;
    }
}
