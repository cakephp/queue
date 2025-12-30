<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Constraint\Queue;

/**
 * JobQueued
 *
 * Asserts that a job of a specific class was queued
 *
 * @internal
 */
class JobQueued extends QueueConstraintBase
{
    /**
     * Checks if job was queued
     *
     * @param mixed $other Job class name
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $jobClass = $other;
        $jobs = $this->getJobs();

        foreach ($jobs as $job) {
            if ($job['jobClass'] === $jobClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        if ($this->at !== null) {
            return sprintf('job #%d was queued', $this->at);
        }

        return 'job was queued';
    }
}
