<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Constraint\Queue;

use Cake\Queue\TestSuite\TestQueueClient;

/**
 * JobCount
 *
 * Asserts that a specific count of jobs were queued
 *
 * @internal
 */
class JobCount extends QueueConstraintBase
{
    /**
     * Checks if job count matches
     *
     * @param mixed $other Expected count
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $expectedCount = $other;
        $actualCount = TestQueueClient::getQueuedJobCount();

        return $actualCount === $expectedCount;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return 'job count matches';
    }
}
