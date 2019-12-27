<?php
namespace Queue\Job;

use Queue\Job\Message;

interface JobInterface
{
    /**
     * Executes logic for {{ name }}Job
     *
     * @param Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string;
}
