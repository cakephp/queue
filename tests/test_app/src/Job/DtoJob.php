<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class DtoJob implements JobInterface
{
    public static ?object $lastDto = null;

    public function execute(Message $message): ?string
    {
        static::$lastDto = $message->getDto();

        return Processor::ACK;
    }
}
