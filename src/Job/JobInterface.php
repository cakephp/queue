<?php
declare(strict_types=1);

namespace Queue\Job;

interface JobInterface
{
    /**
     * Executes logic for {{ name }}Job
     *
     * @param \Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string;
}
