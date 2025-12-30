<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Constraint\Queue;

use Cake\Queue\TestSuite\TestQueueClient;

/**
 * JobQueuedTimes
 *
 * Asserts that a job was queued a specific number of times
 *
 * @internal
 */
class JobQueuedTimes extends QueueConstraintBase
{
    /**
     * Expected number of times
     *
     * @var int
     */
    protected int $times;

    /**
     * Constructor
     *
     * @param int $times Expected number of times
     */
    public function __construct(int $times)
    {
        $this->times = $times;
    }

    /**
     * Checks if job was queued the expected number of times
     *
     * @param mixed $other Job class name
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $jobClass = $other;
        $jobs = TestQueueClient::getQueuedJobsByClass($jobClass);
        $actualCount = count($jobs);

        return $actualCount === $this->times;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return sprintf('job was queued %d times', $this->times);
    }
}
