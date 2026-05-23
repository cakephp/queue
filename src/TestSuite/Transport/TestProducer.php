<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Cake\Queue\TestSuite\TestQueueClient;
use Interop\Queue\Destination;
use Interop\Queue\Message;
use Interop\Queue\Producer;

/**
 * Test Producer
 *
 * Captures messages instead of sending them to a queue.
 */
class TestProducer implements Producer
{
    /**
     * Delivery delay
     */
    protected ?int $deliveryDelay = null;

    /**
     * Time to live
     */
    protected ?int $timeToLive = null;

    /**
     * Priority
     */
    protected ?int $priority = null;

    /**
     * Send message
     *
     * @param \Interop\Queue\Destination $destination Destination
     * @param \Interop\Queue\Message $message Message
     * @return void
     */
    public function send(Destination $destination, Message $message): void
    {
        TestQueueClient::captureMessage(
            $destination,
            $message,
            $this->deliveryDelay,
            $this->timeToLive,
            $this->priority,
        );
    }

    /**
     * Set delivery delay
     *
     * @param int|null $deliveryDelay Delay in milliseconds
     * @return \Interop\Queue\Producer
     */
    public function setDeliveryDelay(?int $deliveryDelay = null): Producer
    {
        $this->deliveryDelay = $deliveryDelay;

        return $this;
    }

    /**
     * Get delivery delay
     *
     * @return int|null
     */
    public function getDeliveryDelay(): ?int
    {
        return $this->deliveryDelay;
    }

    /**
     * Set priority
     *
     * @param int|null $priority Priority
     * @return \Interop\Queue\Producer
     */
    public function setPriority(?int $priority = null): Producer
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get priority
     *
     * @return int|null
     */
    public function getPriority(): ?int
    {
        return $this->priority;
    }

    /**
     * Set time to live
     *
     * @param int|null $timeToLive Time to live in milliseconds
     * @return \Interop\Queue\Producer
     */
    public function setTimeToLive(?int $timeToLive = null): Producer
    {
        $this->timeToLive = $timeToLive;

        return $this;
    }

    /**
     * Get time to live
     *
     * @return int|null
     */
    public function getTimeToLive(): ?int
    {
        return $this->timeToLive;
    }
}
