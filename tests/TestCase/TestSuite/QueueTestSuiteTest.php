<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase\TestSuite;

use Cake\Queue\QueueManager;
use Cake\Queue\TestSuite\QueueTrait as TestQueueTrait;
use Cake\Queue\TestSuite\TestQueueClient;
use Cake\TestSuite\TestCase;
use Enqueue\Client\MessagePriority;
use PHPUnit\Framework\AssertionFailedError;
use TestApp\Job\LogToDebugJob;

/**
 * Queue TestSuite Test
 *
 * Tests both TestQueueClient and QueueTrait functionality
 */
class QueueTestSuiteTest extends TestCase
{
    use TestQueueTrait;

    /**
     * Test replaceAllClients configures the transport
     *
     * @return void
     */
    public function testReplaceAllClients(): void
    {
        if (QueueManager::getConfig('default') === null) {
            QueueManager::setConfig('default', [
                'url' => 'null:',
            ]);
        }

        TestQueueClient::replaceAllClients();

        $config = QueueManager::getConfig('default');
        $url = $config['url'];
        $transport = is_array($url) ? $url['transport'] : $url;
        $this->assertEquals('test:', $transport);
    }

    /**
     * Test assertJobQueued
     *
     * @return void
     */
    public function testAssertJobQueued(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->assertJobQueued(LogToDebugJob::class);
    }

    /**
     * Test assertJobQueued fails when job not queued
     *
     * @return void
     */
    public function testAssertJobQueuedFailsWhenJobNotQueued(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueued(LogToDebugJob::class);
    }

    /**
     * Test assertJobNotQueued
     *
     * @return void
     */
    public function testAssertJobNotQueued(): void
    {
        $this->assertJobNotQueued(LogToDebugJob::class);
    }

    /**
     * Test assertJobNotQueued fails when job is queued
     *
     * @return void
     */
    public function testAssertJobNotQueuedFailsWhenJobQueued(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobNotQueued(LogToDebugJob::class);
    }

    /**
     * Test assertNoJobsQueued
     *
     * @return void
     */
    public function testAssertNoJobsQueued(): void
    {
        $this->assertNoJobsQueued();
    }

    /**
     * Test assertNoJobsQueued fails when jobs queued
     *
     * @return void
     */
    public function testAssertNoJobsQueuedFailsWhenJobsQueued(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->expectException(AssertionFailedError::class);

        $this->assertNoJobsQueued();
    }

    /**
     * Test assertJobCount
     *
     * @return void
     */
    public function testAssertJobCount(): void
    {
        QueueManager::push(LogToDebugJob::class, []);
        QueueManager::push(LogToDebugJob::class, []);

        $this->assertJobCount(2);
    }

    /**
     * Test assertJobCount fails when count mismatch
     *
     * @return void
     */
    public function testAssertJobCountFailsWhenCountMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobCount(2);
    }

    /**
     * Test assertJobQueuedWith
     *
     * @return void
     */
    public function testAssertJobQueuedWith(): void
    {
        QueueManager::push(LogToDebugJob::class, ['key' => 'value', 'id' => 123]);

        $this->assertJobQueuedWith(LogToDebugJob::class, ['key' => 'value', 'id' => 123]);
    }

    /**
     * Test assertJobQueuedWith fails when data mismatch
     *
     * @return void
     */
    public function testAssertJobQueuedWithFailsWhenDataMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, ['key' => 'value']);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueuedWith(LogToDebugJob::class, ['key' => 'different']);
    }

    /**
     * Test assertJobQueuedToQueue
     *
     * @return void
     */
    public function testAssertJobQueuedToQueue(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['queue' => 'high-priority']);

        $this->assertJobQueuedToQueue('high-priority', LogToDebugJob::class);
    }

    /**
     * Test assertJobQueuedToQueue fails when queue mismatch
     *
     * @return void
     */
    public function testAssertJobQueuedToQueueFailsWhenQueueMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['queue' => 'low-priority']);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueuedToQueue('high-priority', LogToDebugJob::class);
    }

    /**
     * Test assertJobQueuedWithDelay
     *
     * @return void
     */
    public function testAssertJobQueuedWithDelay(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['delay' => 60]);

        $this->assertJobQueuedWithDelay(LogToDebugJob::class, 60);
    }

    /**
     * Test assertJobQueuedWithDelay fails when delay mismatch
     *
     * @return void
     */
    public function testAssertJobQueuedWithDelayFailsWhenDelayMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['delay' => 30]);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueuedWithDelay(LogToDebugJob::class, 60);
    }

    /**
     * Test assertJobQueuedWithPriority
     *
     * @return void
     */
    public function testAssertJobQueuedWithPriority(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['priority' => MessagePriority::HIGH]);

        $this->assertJobQueuedWithPriority(LogToDebugJob::class, MessagePriority::HIGH);
    }

    /**
     * Test assertJobQueuedWithPriority fails when priority mismatch
     *
     * @return void
     */
    public function testAssertJobQueuedWithPriorityFailsWhenPriorityMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['priority' => MessagePriority::LOW]);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueuedWithPriority(LogToDebugJob::class, MessagePriority::HIGH);
    }

    /**
     * Test assertJobQueuedTimes
     *
     * @return void
     */
    public function testAssertJobQueuedTimes(): void
    {
        QueueManager::push(LogToDebugJob::class, []);
        QueueManager::push(LogToDebugJob::class, []);
        QueueManager::push(LogToDebugJob::class, []);

        $this->assertJobQueuedTimes(LogToDebugJob::class, 3);
    }

    /**
     * Test assertJobQueuedTimes fails when count mismatch
     *
     * @return void
     */
    public function testAssertJobQueuedTimesFailsWhenCountMismatch(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->expectException(AssertionFailedError::class);

        $this->assertJobQueuedTimes(LogToDebugJob::class, 2);
    }

    /**
     * Test getQueuedJobs
     *
     * @return void
     */
    public function testGetQueuedJobs(): void
    {
        QueueManager::push(LogToDebugJob::class, ['data' => 'value']);

        $jobs = $this->getQueuedJobs();

        $this->assertCount(1, $jobs);
        $this->assertEquals(LogToDebugJob::class, $jobs[0]['jobClass']);
        $this->assertEquals('execute', $jobs[0]['method']);
        $this->assertEquals(['data' => 'value'], $jobs[0]['data']);
        $this->assertEquals('default', $jobs[0]['options']['queue']);
        $this->assertEquals('default', $jobs[0]['options']['config']);
    }

    /**
     * Test getQueuedJobsByClass
     *
     * @return void
     */
    public function testGetQueuedJobsByClass(): void
    {
        QueueManager::push(LogToDebugJob::class, ['job' => 1]);
        QueueManager::push(LogToDebugJob::class, ['job' => 2]);

        $this->assertJobQueued(LogToDebugJob::class);
        $this->assertJobQueuedTimes(LogToDebugJob::class, 2);

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);

        $this->assertCount(2, $jobs);
    }

    /**
     * Test getQueuedJobsByQueue
     *
     * @return void
     */
    public function testGetQueuedJobsByQueue(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['queue' => 'high-priority']);
        QueueManager::push(LogToDebugJob::class, [], ['queue' => 'low-priority']);
        QueueManager::push(LogToDebugJob::class, [], ['queue' => 'high-priority']);

        $highPriorityJobs = $this->getQueuedJobsByQueue('high-priority');
        $lowPriorityJobs = $this->getQueuedJobsByQueue('low-priority');

        $this->assertCount(2, $highPriorityJobs);
        $this->assertCount(1, $lowPriorityJobs);
    }

    /**
     * Test getQueuedJobsByConfig
     *
     * @return void
     */
    public function testGetQueuedJobsByConfig(): void
    {
        QueueManager::setConfig('test', ['url' => 'null:']);
        TestQueueClient::replaceAllClients();

        QueueManager::push(LogToDebugJob::class, [], ['config' => 'default']);
        QueueManager::push(LogToDebugJob::class, [], ['config' => 'test']);
        QueueManager::push(LogToDebugJob::class, [], ['config' => 'default']);

        $defaultJobs = $this->getQueuedJobsByConfig('default');
        $testJobs = $this->getQueuedJobsByConfig('test');

        $this->assertCount(2, $defaultJobs);
        $this->assertCount(1, $testJobs);
    }

    /**
     * Test clearQueuedJobs
     *
     * @return void
     */
    public function testClearQueuedJobs(): void
    {
        QueueManager::push(LogToDebugJob::class, []);

        $this->assertCount(1, $this->getQueuedJobs());

        TestQueueClient::clearQueuedJobs();

        $this->assertCount(0, $this->getQueuedJobs());
    }

    /**
     * Test job captured with delay
     *
     * @return void
     */
    public function testJobCapturedWithDelay(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['delay' => 60]);

        $jobs = $this->getQueuedJobs();

        $this->assertCount(1, $jobs);
        $this->assertEquals(60, $jobs[0]['options']['delay']);
    }

    /**
     * Test job captured with priority
     *
     * @return void
     */
    public function testJobCapturedWithPriority(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['priority' => MessagePriority::HIGH]);

        $jobs = $this->getQueuedJobs();

        $this->assertCount(1, $jobs);
        $this->assertEquals(MessagePriority::HIGH, $jobs[0]['options']['priority']);
    }

    /**
     * Test job captured with expires
     *
     * @return void
     */
    public function testJobCapturedWithExpires(): void
    {
        QueueManager::push(LogToDebugJob::class, [], ['expires' => 3600]);

        $jobs = $this->getQueuedJobs();

        $this->assertCount(1, $jobs);
        $this->assertEquals(3600, $jobs[0]['options']['expires']);
    }
}
