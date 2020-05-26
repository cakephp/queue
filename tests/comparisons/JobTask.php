<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

/**
 * Upload job
 */
class UploadJob
{
    /**
     * Executes logic for UploadJob
     *
     * @param \Cake\Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): string
    {
        return Processor::ACK;
    }
}
