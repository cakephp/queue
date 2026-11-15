<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Attribute\ShouldBeUnique;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

#[ShouldBeUnique]
class AttributeUniqueJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return Processor::ACK;
    }
}
