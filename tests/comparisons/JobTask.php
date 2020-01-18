<?php
declare(strict_types=1);

namespace TestApp\Job;

use Queue\Job\Message;
use Interop\Queue\Processor;

/**
 * Upload job
 */
class UploadJob
{
    /**
     * Executes logic for UploadJob
     *
     * @param \Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): string
    {
        return Processor::ACK;
    }
}
