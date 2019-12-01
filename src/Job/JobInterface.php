<?php
namespace Queue\Job;

use Queue\Queue\JobData;

interface JobInterface
{
    /**
     * Executes logic for {{ name }}Job
     *
     * @return string
     */
    public function execute(JobData $job): ?string;
}
