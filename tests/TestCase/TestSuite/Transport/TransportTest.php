<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase\TestSuite\Transport;

use Cake\Queue\TestSuite\Transport\TestConnectionFactory;
use Cake\Queue\TestSuite\Transport\TestConsumer;
use Cake\Queue\TestSuite\Transport\TestContext;
use Cake\Queue\TestSuite\Transport\TestDestination;
use Cake\Queue\TestSuite\Transport\TestMessage;
use Cake\Queue\TestSuite\Transport\TestProducer;
use Cake\Queue\TestSuite\Transport\TestSubscriptionConsumer;
use Cake\TestSuite\TestCase;

class TransportTest extends TestCase
{
    public function testTestConnectionFactory(): void
    {
        $factory = new TestConnectionFactory();
        $context = $factory->createContext();
        $this->assertInstanceOf(TestContext::class, $context);
        $factory->close();
    }

    public function testTestContext(): void
    {
        $context = new TestContext();

        $message = $context->createMessage('body', ['prop' => 'value'], ['header' => 'value']);
        $this->assertInstanceOf(TestMessage::class, $message);
        $this->assertEquals('body', $message->getBody());

        $queue = $context->createQueue('test-queue');
        $this->assertInstanceOf(TestDestination::class, $queue);
        $this->assertEquals('test-queue', $queue->getQueueName());

        $topic = $context->createTopic('test-topic');
        $this->assertInstanceOf(TestDestination::class, $topic);
        $this->assertEquals('test-topic', $topic->getTopicName());

        $producer = $context->createProducer();
        $this->assertInstanceOf(TestProducer::class, $producer);

        $consumer = $context->createConsumer($queue);
        $this->assertInstanceOf(TestConsumer::class, $consumer);

        $topicConsumer = $context->createConsumer($topic);
        $this->assertInstanceOf(TestConsumer::class, $topicConsumer);

        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $this->assertInstanceOf(TestSubscriptionConsumer::class, $subscriptionConsumer);

        $tempQueue = $context->createTemporaryQueue();
        $this->assertInstanceOf(TestDestination::class, $tempQueue);
        $this->assertStringStartsWith('temp_', $tempQueue->getQueueName());

        $context->purgeQueue($queue);
        $context->close();
    }

    public function testTestMessage(): void
    {
        $message = new TestMessage('body', ['prop' => 'value'], ['header' => 'value']);

        $this->assertEquals('body', $message->getBody());
        $message->setBody('new-body');
        $this->assertEquals('new-body', $message->getBody());

        $this->assertEquals('value', $message->getProperty('prop'));
        $this->assertEquals('default', $message->getProperty('missing', 'default'));
        $message->setProperty('new-prop', 'new-value');
        $this->assertEquals('new-value', $message->getProperty('new-prop'));

        $this->assertEquals('value', $message->getHeader('header'));
        $this->assertEquals('default', $message->getHeader('missing', 'default'));
        $message->setHeader('new-header', 'new-value');
        $this->assertEquals('new-value', $message->getHeader('new-header'));

        $message->setProperties(['a' => 'b']);
        $this->assertEquals(['a' => 'b'], $message->getProperties());

        $message->setHeaders(['x' => 'y']);
        $this->assertEquals(['x' => 'y'], $message->getHeaders());

        $this->assertFalse($message->isRedelivered());

        $message->setCorrelationId('corr-123');
        $this->assertEquals('corr-123', $message->getCorrelationId());

        $message->setMessageId('msg-123');
        $this->assertEquals('msg-123', $message->getMessageId());

        $message->setTimestamp(1234567890);
        $this->assertEquals(1234567890, $message->getTimestamp());

        $message->setReplyTo('reply-to');
        $this->assertEquals('reply-to', $message->getReplyTo());
    }

    public function testTestProducer(): void
    {
        $producer = new TestProducer();
        $destination = new TestDestination('test-queue');
        $message = new TestMessage('body');

        $producer->setDeliveryDelay(1000);
        $this->assertEquals(1000, $producer->getDeliveryDelay());

        $producer->setPriority(5);
        $this->assertEquals(5, $producer->getPriority());

        $producer->setTimeToLive(2000);
        $this->assertEquals(2000, $producer->getTimeToLive());

        $producer->send($destination, $message);
    }

    public function testTestConsumer(): void
    {
        $subscriptionConsumer = new TestSubscriptionConsumer();
        $queue = new TestDestination('test-queue');
        $consumer = new TestConsumer($queue);

        $this->assertEquals($queue, $consumer->getQueue());
        $this->assertNull($consumer->receive());
        $this->assertNull($consumer->receiveNoWait());

        $message = new TestMessage('body');
        $consumer->acknowledge($message);
        $consumer->reject($message, true);

        $subscriptionConsumer->consume(1000);
        $subscriptionConsumer->subscribe($consumer, function () {
        });
        $subscriptionConsumer->unsubscribe($consumer);
        $subscriptionConsumer->unsubscribeAll();
    }

    public function testTestDestination(): void
    {
        $destination = new TestDestination('test-name');

        $this->assertEquals('test-name', $destination->getQueueName());
        $this->assertEquals('test-name', $destination->getTopicName());
        $this->assertEquals('test-name', $destination->getDestinationName());
    }
}
