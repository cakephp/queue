<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite;

use Cake\Queue\QueueManager;
use Enqueue\Client\Resources as ClientResources;
use Enqueue\Resources;
use Interop\Queue\Destination;
use Interop\Queue\Message;
use Interop\Queue\Queue;
use Interop\Queue\Topic;

/**
 * Test Queue Client
 *
 * Captures queued jobs instead of actually queuing them for testing purposes.
 * Similar to TestEmailTransport for email testing.
 *
 * Uses a custom Enqueue transport (`test:`) that captures messages at the transport layer.
 *
 * Usage:
 * ```
 * // In test setup (via QueueTrait)
 * TestQueueClient::replaceAllClients();
 *
 * // Queue as normal
 * QueueManager::push('MyJob', ['data' => 'value']);
 *
 * // Make assertions
 * $jobs = TestQueueClient::getQueuedJobs();
 * ```
 */
class TestQueueClient
{
    /**
     * Captured queued jobs
     *
     * @var array<array<string, mixed>>
     */
    protected static array $queuedJobs = [];

    /**
     * Transport registration flag
     *
     * @var bool
     */
    protected static bool $registered = false;

    /**
     * Replace all queue clients with test transport
     *
     * Similar to TestEmailTransport::replaceAllTransports()
     *
     * @return void
     */
    public static function replaceAllClients(): void
    {
        if (!static::$registered) {
            Resources::addConnection(
                Transport\TestConnectionFactory::class,
                ['test'],
                [],
                'cakephp/queue-testsuite',
            );
            ClientResources::addDriver(
                Transport\TestDriver::class,
                ['test'],
                [],
                ['cakephp/queue-testsuite'],
            );
            static::$registered = true;
        }

        $configured = QueueManager::configured();

        foreach ($configured as $configName) {
            $config = QueueManager::getConfig($configName);
            if ($config === null) {
                continue;
            }

            $config['url'] = 'test:';
            QueueManager::drop($configName);
            QueueManager::setConfig($configName, $config);
        }
    }

    /**
     * Capture message from transport
     *
     * Called by TestProducer when a message is sent.
     *
     * @param \Interop\Queue\Destination $destination Destination
     * @param \Interop\Queue\Message $message Message
     * @param int|null $deliveryDelay Delivery delay from producer (in milliseconds)
     * @param int|null $timeToLive Time to live from producer (in milliseconds)
     * @param int|null $producerPriority Priority from producer
     * @return void
     */
    public static function captureMessage(
        Destination $destination,
        Message $message,
        ?int $deliveryDelay = null,
        ?int $timeToLive = null,
        ?int $producerPriority = null,
    ): void {
        $body = static::extractMessageBody($message);
        $classData = $body['class'] ?? null;
        $jobClass = is_array($classData) ? $classData[0] : null;
        $method = is_array($classData) && isset($classData[1]) ? $classData[1] : 'execute';
        $data = $body['data'] ?? ($body['args'][0] ?? []);

        $requeueOptions = $body['requeueOptions'] ?? [];
        $configName = $requeueOptions['config'] ?? 'default';

        $queueName = 'default';
        if ($destination instanceof Queue) {
            $queueName = $destination->getQueueName();
        } elseif ($destination instanceof Topic) {
            $queueName = $destination->getTopicName();
        }
        $queueName = $requeueOptions['queue'] ?? $queueName;

        $properties = $message->getProperties();
        $delay = $properties['enqueue.delay'] ?? null;
        $expires = $properties['enqueue.expire'] ?? null;
        $priority = $properties['enqueue.priority'] ??
            $producerPriority ??
            $requeueOptions['priority'] ??
            null;

        if ($delay !== null) {
            $delay = (int)$delay;
        } elseif ($deliveryDelay !== null) {
            $delay = (int)($deliveryDelay / 1000);
        }

        if ($expires !== null) {
            $expires = (int)$expires;
        } elseif ($timeToLive !== null) {
            $expires = (int)($timeToLive / 1000);
        }

        static::$queuedJobs[] = [
            'jobClass' => $jobClass,
            'method' => $method,
            'data' => $data,
            'options' => [
                'config' => $configName,
                'queue' => $queueName,
                'delay' => $delay,
                'expires' => $expires,
                'priority' => $priority,
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * Extract message body
     *
     * @param \Interop\Queue\Message $message Message
     * @return array<string, mixed>
     */
    protected static function extractMessageBody(Message $message): array
    {
        $body = $message->getBody();
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Get all queued jobs
     *
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobs(): array
    {
        return static::$queuedJobs;
    }

    /**
     * Get queued jobs by job class
     *
     * @param string $jobClass Job class name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobsByClass(string $jobClass): array
    {
        $filtered = array_filter(static::$queuedJobs, function ($job) use ($jobClass) {
            return $job['jobClass'] === $jobClass;
        });

        return array_values($filtered);
    }

    /**
     * Get queued jobs by queue name
     *
     * @param string $queue Queue name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobsByQueue(string $queue): array
    {
        $filtered = array_filter(static::$queuedJobs, function ($job) use ($queue) {
            return ($job['options']['queue'] ?? 'default') === $queue;
        });

        return array_values($filtered);
    }

    /**
     * Get queued jobs by config name
     *
     * @param string $config Config name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobsByConfig(string $config): array
    {
        $filtered = array_filter(static::$queuedJobs, function ($job) use ($config) {
            return ($job['options']['config'] ?? 'default') === $config;
        });

        return array_values($filtered);
    }

    /**
     * Clear all queued jobs
     *
     * @return void
     */
    public static function clearQueuedJobs(): void
    {
        static::$queuedJobs = [];
    }

    /**
     * Get count of queued jobs
     *
     * @return int
     */
    public static function getQueuedJobCount(): int
    {
        return count(static::$queuedJobs);
    }
}
