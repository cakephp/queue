<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;

class UnknownStringJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return 'whatever';
    }
}
