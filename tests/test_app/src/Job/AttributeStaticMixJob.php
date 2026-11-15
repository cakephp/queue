<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Attribute\MaxAttempts;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

#[MaxAttempts(9)]
class AttributeStaticMixJob implements JobInterface
{
    public static $maxAttempts = 3;

    public function execute(Message $message): ?string
    {
        return Processor::ACK;
    }
}
