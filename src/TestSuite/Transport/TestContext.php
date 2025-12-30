<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Destination;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use Interop\Queue\Topic;

/**
 * Test Context
 *
 * Provides test implementations of Enqueue context interfaces.
 */
class TestContext implements Context
{
    /**
     * Cached producer instance
     *
     * @var \Interop\Queue\Producer|null
     */
    protected ?Producer $producer = null;

    /**
     * Create message
     *
     * @param string $body Message body
     * @param array<string, mixed> $properties Message properties
     * @param array<string, mixed> $headers Message headers
     * @return \Interop\Queue\Message
     */
    public function createMessage(string $body = '', array $properties = [], array $headers = []): Message
    {
        return new TestMessage($body, $properties, $headers);
    }

    /**
     * Create queue
     *
     * @param string $name Queue name
     * @return \Interop\Queue\Queue
     */
    public function createQueue(string $name): Queue
    {
        return new TestDestination($name);
    }

    /**
     * Create topic
     *
     * @param string $name Topic name
     * @return \Interop\Queue\Topic
     */
    public function createTopic(string $name): Topic
    {
        return new TestDestination($name);
    }

    /**
     * Create producer
     *
     * @return \Interop\Queue\Producer
     */
    public function createProducer(): Producer
    {
        if ($this->producer === null) {
            $this->producer = new TestProducer();
        }

        return $this->producer;
    }

    /**
     * Create consumer
     *
     * @param \Interop\Queue\Destination $destination Destination
     * @return \Interop\Queue\Consumer
     */
    public function createConsumer(Destination $destination): Consumer
    {
        if ($destination instanceof Queue) {
            return new TestConsumer($destination);
        }

        $queueName = $destination instanceof Topic
            ? $destination->getTopicName()
            : 'default';

        return new TestConsumer(new TestDestination($queueName));
    }

    /**
     * Create subscription consumer
     *
     * @return \Interop\Queue\SubscriptionConsumer
     */
    public function createSubscriptionConsumer(): SubscriptionConsumer
    {
        return new TestSubscriptionConsumer();
    }

    /**
     * Create temporary queue
     *
     * @return \Interop\Queue\Queue
     */
    public function createTemporaryQueue(): Queue
    {
        return new TestDestination('temp_' . uniqid());
    }

    /**
     * Purge queue
     *
     * @param \Interop\Queue\Queue $queue Queue to purge
     * @return void
     */
    public function purgeQueue(Queue $queue): void
    {
    }

    /**
     * Close context
     *
     * @return void
     */
    public function close(): void
    {
    }
}
