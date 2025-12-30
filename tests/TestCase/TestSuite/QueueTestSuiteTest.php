<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase\TestSuite;

use Cake\Queue\QueueManager;
use Cake\Queue\TestSuite\Constraint\Queue\JobQueued;
use Cake\Queue\TestSuite\QueueTrait as TestQueueTrait;
use Cake\Queue\TestSuite\TestQueueClient;
use Cake\Queue\TestSuite\Transport\TestConsumer;
use Cake\Queue\TestSuite\Transport\TestContext;
use Cake\Queue\TestSuite\Transport\TestDestination;
use Cake\TestSuite\TestCase;
use Enqueue\Client\MessagePriority;
use Interop\Queue\Topic;
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

    /**
     * Test getQueuedJobCount
     *
     * @return void
     */
    public function testGetQueuedJobCount(): void
    {
        $this->assertEquals(0, TestQueueClient::getQueuedJobCount());

        QueueManager::push(LogToDebugJob::class, []);
        $this->assertEquals(1, TestQueueClient::getQueuedJobCount());

        QueueManager::push(LogToDebugJob::class, []);
        $this->assertEquals(2, TestQueueClient::getQueuedJobCount());
    }

    /**
     * Test replaceAllClients with multiple configs
     *
     * @return void
     */
    public function testReplaceAllClientsWithMultipleConfigs(): void
    {
        QueueManager::setConfig('test1', ['url' => 'null:']);
        QueueManager::setConfig('test2', ['url' => 'null:']);

        TestQueueClient::replaceAllClients();

        $config1 = QueueManager::getConfig('test1');
        $config2 = QueueManager::getConfig('test2');

        $this->assertNotNull($config1);
        $this->assertNotNull($config2);

        $url1 = $config1['url'];
        $url2 = $config2['url'];
        $transport1 = is_array($url1) ? $url1['transport'] : $url1;
        $transport2 = is_array($url2) ? $url2['transport'] : $url2;

        $this->assertEquals('test:', $transport1);
        $this->assertEquals('test:', $transport2);
    }

    /**
     * Test replaceAllClients handles null config gracefully
     *
     * @return void
     */
    public function testReplaceAllClientsHandlesNullConfig(): void
    {
        QueueManager::setConfig('test-null', ['url' => 'null:']);
        QueueManager::drop('test-null');

        TestQueueClient::replaceAllClients();

        $this->assertNull(QueueManager::getConfig('test-null'));
    }

    /**
     * Test job captured with custom method
     *
     * @return void
     */
    public function testJobCapturedWithCustomMethod(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class, 'customMethod'],
            'data' => ['test' => 'value'],
        ]);

        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage($body);

        TestQueueClient::captureMessage($destination, $message);

        $jobs = $this->getQueuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals(LogToDebugJob::class, $jobs[0]['jobClass']);
        $this->assertEquals('customMethod', $jobs[0]['method']);
    }

    /**
     * Test job captured with topic destination
     *
     * @return void
     */
    public function testJobCapturedWithTopicDestination(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class],
            'data' => [],
        ]);

        $context = new TestContext();
        $destination = $context->createTopic('test-topic');
        $message = $context->createMessage($body);

        TestQueueClient::captureMessage($destination, $message);

        $jobs = $this->getQueuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals('test-topic', $jobs[0]['options']['queue']);
    }

    /**
     * Test job captured with requeue options
     *
     * @return void
     */
    public function testJobCapturedWithRequeueOptions(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class],
            'data' => [],
            'requeueOptions' => [
                'config' => 'custom-config',
                'queue' => 'custom-queue',
                'priority' => 'high',
            ],
        ]);

        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage($body);

        TestQueueClient::captureMessage($destination, $message);

        $jobs = $this->getQueuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals('custom-config', $jobs[0]['options']['config']);
        $this->assertEquals('custom-queue', $jobs[0]['options']['queue']);
        $this->assertEquals('high', $jobs[0]['options']['priority']);
    }

    /**
     * Test job captured with message properties for delay and expires
     *
     * @return void
     */
    public function testJobCapturedWithMessageProperties(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class],
            'data' => [],
        ]);

        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage($body, [
            'enqueue.delay' => '5',
            'enqueue.expire' => '10',
            'enqueue.priority' => '5',
        ]);

        TestQueueClient::captureMessage($destination, $message, 3000, 6000, 3);

        $jobs = $this->getQueuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals(5, $jobs[0]['options']['delay']);
        $this->assertEquals(10, $jobs[0]['options']['expires']);
        $this->assertEquals(5, $jobs[0]['options']['priority']);
    }

    /**
     * Test job captured with delivery delay and time to live from producer
     *
     * @return void
     */
    public function testJobCapturedWithProducerDelayAndTtl(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class],
            'data' => [],
        ]);

        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage($body);

        TestQueueClient::captureMessage($destination, $message, 5000, 10000, 3);

        $jobs = $this->getQueuedJobs();
        $this->assertCount(1, $jobs);
        $this->assertEquals(5, $jobs[0]['options']['delay']);
        $this->assertEquals(10, $jobs[0]['options']['expires']);
        $this->assertEquals(3, $jobs[0]['options']['priority']);
    }

    /**
     * Test extractMessageBody with args fallback
     *
     * @return void
     */
    public function testExtractMessageBodyWithArgs(): void
    {
        $body = json_encode([
            'class' => [LogToDebugJob::class],
            'args' => [['test' => 'value']],
        ]);

        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage($body);

        TestQueueClient::captureMessage($destination, $message);

        $jobs = $this->getQueuedJobs();
        $this->assertEquals(['test' => 'value'], $jobs[0]['data']);
    }

    /**
     * Test extractMessageBody with invalid JSON
     *
     * @return void
     */
    public function testExtractMessageBodyWithInvalidJson(): void
    {
        $context = new TestContext();
        $destination = $context->createQueue('default');
        $message = $context->createMessage('invalid json');

        TestQueueClient::captureMessage($destination, $message);

        $jobs = $this->getQueuedJobs();
        $this->assertNull($jobs[0]['jobClass']);
    }

    /**
     * Test createConsumer with other destination
     *
     * @return void
     */
    public function testCreateConsumerWithOtherDestination(): void
    {
        $context = new TestContext();
        $destination = new TestDestination('test');

        $consumer = $context->createConsumer($destination);

        $this->assertInstanceOf(TestConsumer::class, $consumer);
    }

    /**
     * Test createConsumer with Topic destination (not Queue)
     *
     * @return void
     */
    public function testCreateConsumerWithTopicOnlyDestination(): void
    {
        $context = new TestContext();
        $topic = $this->createMock(Topic::class);
        $topic->method('getTopicName')->willReturn('test-topic');

        $consumer = $context->createConsumer($topic);

        $this->assertInstanceOf(TestConsumer::class, $consumer);
    }

    /**
     * Test QueueConstraintBase with at parameter
     *
     * @return void
     */
    public function testQueueConstraintBaseWithAt(): void
    {
        QueueManager::push(LogToDebugJob::class, []);
        QueueManager::push(LogToDebugJob::class, []);

        $constraint = new JobQueued(0);
        $this->assertTrue($constraint->matches(LogToDebugJob::class));
        $this->assertEquals('job #0 was queued', $constraint->toString());

        $constraint = new JobQueued(99);
        $this->assertFalse($constraint->matches(LogToDebugJob::class));
    }
}
