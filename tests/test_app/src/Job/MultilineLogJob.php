<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Log\LogTrait;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;
use Psr\Log\LogLevel;

class MultilineLogJob implements JobInterface
{
    use LogTrait;

    public function execute(Message $message): ?string
    {
        $this->log('Job execution started', LogLevel::INFO);
        $this->log('Processing step 1 completed', LogLevel::INFO);
        $this->log('Processing step 2 completed', LogLevel::INFO);
        $this->log('Processing step 3 completed', LogLevel::INFO);
        $this->log('Job execution finished', LogLevel::INFO);

        return Processor::ACK;
    }
}
