<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Constraint\Queue;

use Cake\Queue\TestSuite\TestQueueClient;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Base class for all queue assertion constraints
 *
 * @internal
 */
abstract class QueueConstraintBase extends Constraint
{
    /**
     * Job index to check
     */
    protected ?int $at = null;

    /**
     * Constructor
     *
     * @param int|null $at Optional index of specific job to check
     */
    public function __construct(?int $at = null)
    {
        $this->at = $at;
    }

    /**
     * Get the jobs or job to check
     *
     * @return array<array<string, mixed>>
     */
    protected function getJobs(): array
    {
        $jobs = TestQueueClient::getQueuedJobs();

        if ($this->at !== null) {
            if (!isset($jobs[$this->at])) {
                return [];
            }

            return [$jobs[$this->at]];
        }

        return $jobs;
    }
}
