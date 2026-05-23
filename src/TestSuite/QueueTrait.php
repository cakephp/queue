<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite;

use Cake\Queue\QueueManager;
use Cake\Queue\TestSuite\Constraint\Queue\JobCount;
use Cake\Queue\TestSuite\Constraint\Queue\JobQueued;
use Cake\Queue\TestSuite\Constraint\Queue\JobQueuedTimes;
use Cake\Queue\TestSuite\Constraint\Queue\NoJobQueued;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Queue Trait
 *
 * Make assertions on jobs queued through QueueManager.
 *
 * After adding the trait to your test case, all jobs will be captured
 * instead of being queued, allowing you to make assertions.
 *
 * Usage:
 * ```
 * class MyTest extends TestCase
 * {
 *     use QueueTrait;
 *
 *     public function testJobQueued(): void
 *     {
 *         QueueManager::push('MyJob', ['data' => 'value']);
 *
 *         $this->assertJobQueued('MyJob');
 *         $this->assertJobQueuedWith('MyJob', ['data' => 'value']);
 *     }
 * }
 * ```
 */
trait QueueTrait
{
    /**
     * Setup test queue client
     *
     * Replaces all queue configs with test transport
     * to capture jobs instead of queuing them.
     *
     * @return void
     */
    #[Before]
    public function setupTestQueueClient(): void
    {
        TestQueueClient::clearQueuedJobs();

        if (!QueueManager::configured()) {
            QueueManager::setConfig('default', [
                'url' => 'null:',
            ]);
        }

        TestQueueClient::replaceAllClients();
    }

    /**
     * Cleanup queued jobs
     *
     * Clears all captured jobs after each test.
     *
     * @return void
     */
    #[After]
    public function cleanupQueueTrait(): void
    {
        TestQueueClient::clearQueuedJobs();
    }

    /**
     * Assert a job was queued
     *
     * @param string $jobClass Job class name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueued(string $jobClass, string $message = ''): void
    {
        $this->assertThat($jobClass, new JobQueued(), $message);
    }

    /**
     * Assert a job was not queued
     *
     * @param string $jobClass Job class name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobNotQueued(string $jobClass, string $message = ''): void
    {
        $jobs = TestQueueClient::getQueuedJobsByClass($jobClass);
        $this->assertEmpty(
            $jobs,
            $message ?: sprintf('Job %s was queued unexpectedly', $jobClass),
        );
    }

    /**
     * Assert no jobs were queued
     *
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertNoJobsQueued(string $message = ''): void
    {
        $this->assertThat(null, new NoJobQueued(), $message);
    }

    /**
     * Assert a specific count of jobs were queued
     *
     * @param int $count Expected job count
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobCount(int $count, string $message = ''): void
    {
        $this->assertThat($count, new JobCount(), $message);
    }

    /**
     * Assert a job was queued with specific data
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Expected data
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueuedWith(
        string $jobClass,
        array $data,
        string $message = '',
    ): void {
        $jobs = TestQueueClient::getQueuedJobsByClass($jobClass);
        $this->assertNotEmpty($jobs, sprintf('Job %s was not queued', $jobClass));

        $found = false;
        foreach ($jobs as $job) {
            if ($job['data'] === $data) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            $message ?: sprintf('Job %s was not queued with expected data', $jobClass),
        );
    }

    /**
     * Assert a job was queued to a specific queue
     *
     * @param string $queue Queue name
     * @param string $jobClass Job class name
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueuedToQueue(
        string $queue,
        string $jobClass,
        string $message = '',
    ): void {
        $jobs = TestQueueClient::getQueuedJobsByQueue($queue);
        $found = false;
        foreach ($jobs as $job) {
            if ($job['jobClass'] === $jobClass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            $message ?: sprintf('Job %s was not queued to queue %s', $jobClass, $queue),
        );
    }

    /**
     * Assert a job was queued with delay
     *
     * @param string $jobClass Job class name
     * @param int $delay Expected delay in seconds
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueuedWithDelay(
        string $jobClass,
        int $delay,
        string $message = '',
    ): void {
        $jobs = TestQueueClient::getQueuedJobsByClass($jobClass);
        $this->assertNotEmpty($jobs, sprintf('Job %s was not queued', $jobClass));

        $found = false;
        foreach ($jobs as $job) {
            if (($job['options']['delay'] ?? null) === $delay) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            $message ?: sprintf('Job %s was not queued with delay %d', $jobClass, $delay),
        );
    }

    /**
     * Assert a job was queued with priority
     *
     * @param string $jobClass Job class name
     * @param string|int $priority Expected priority
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueuedWithPriority(
        string $jobClass,
        int|string $priority,
        string $message = '',
    ): void {
        $jobs = TestQueueClient::getQueuedJobsByClass($jobClass);
        $this->assertNotEmpty($jobs, sprintf('Job %s was not queued', $jobClass));

        $found = false;
        foreach ($jobs as $job) {
            if (($job['options']['priority'] ?? null) === $priority) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            $message ?: sprintf('Job %s was not queued with priority %s', $jobClass, $priority),
        );
    }

    /**
     * Assert a job was queued a specific number of times
     *
     * @param string $jobClass Job class name
     * @param int $times Expected number of times
     * @param string $message Optional assertion message
     * @return void
     */
    public function assertJobQueuedTimes(string $jobClass, int $times, string $message = ''): void
    {
        $this->assertThat($jobClass, new JobQueuedTimes($times), $message);
    }

    /**
     * Get all queued jobs
     *
     * @return array<array<string, mixed>>
     */
    public function getQueuedJobs(): array
    {
        return TestQueueClient::getQueuedJobs();
    }

    /**
     * Get queued jobs by class
     *
     * @param string $jobClass Job class name
     * @return array<array<string, mixed>>
     */
    public function getQueuedJobsByClass(string $jobClass): array
    {
        return TestQueueClient::getQueuedJobsByClass($jobClass);
    }

    /**
     * Get queued jobs by queue name
     *
     * @param string $queue Queue name
     * @return array<array<string, mixed>>
     */
    public function getQueuedJobsByQueue(string $queue): array
    {
        return TestQueueClient::getQueuedJobsByQueue($queue);
    }

    /**
     * Get queued jobs by config name
     *
     * @param string $config Config name
     * @return array<array<string, mixed>>
     */
    public function getQueuedJobsByConfig(string $config): array
    {
        return TestQueueClient::getQueuedJobsByConfig($config);
    }
}
