<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Attribute\Config;
use Cake\Queue\Attribute\Delay;
use Cake\Queue\Attribute\Expires;
use Cake\Queue\Attribute\MaxAttempts;
use Cake\Queue\Attribute\Priority;
use Cake\Queue\Attribute\Queue;
use Cake\Queue\Attribute\ShouldBeUnique;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

#[Queue('attribute-queue')]
#[Config('test')]
#[Delay(90)]
#[Expires(3600)]
#[Priority('high')]
#[MaxAttempts(7)]
#[ShouldBeUnique]
class AttributeFullJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return Processor::ACK;
    }
}
