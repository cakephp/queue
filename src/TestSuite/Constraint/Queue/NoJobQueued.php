<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Constraint\Queue;

use Cake\Queue\TestSuite\TestQueueClient;

/**
 * NoJobQueued
 *
 * Asserts that no jobs were queued
 *
 * @internal
 */
class NoJobQueued extends QueueConstraintBase
{
    /**
     * Checks if no jobs were queued
     *
     * @param mixed $other Ignored
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        return TestQueueClient::getQueuedJobCount() === 0;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return 'no jobs were queued';
    }
}
