<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use RuntimeException;

class ThrowJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        throw new RuntimeException('Job failed.');
    }
}
