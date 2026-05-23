<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Interop\Queue\Queue;

/**
 * Test Consumer
 *
 * Minimal consumer implementation for testing.
 */
class TestConsumer implements Consumer
{
    /**
     * Queue
     */
    protected Queue $queue;

    /**
     * Constructor
     *
     * @param \Interop\Queue\Queue $queue Queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Get queue
     *
     * @return \Interop\Queue\Queue
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * Receive message
     *
     * @param int|null $timeout Timeout in milliseconds
     * @return \Interop\Queue\Message|null
     */
    public function receive(?int $timeout = null): ?Message
    {
        return null;
    }

    /**
     * Receive no wait
     *
     * @return \Interop\Queue\Message|null
     */
    public function receiveNoWait(): ?Message
    {
        return null;
    }

    /**
     * Acknowledge message
     *
     * @param \Interop\Queue\Message $message Message
     * @return void
     */
    public function acknowledge(Message $message): void
    {
    }

    /**
     * Reject message
     *
     * @param \Interop\Queue\Message $message Message
     * @param bool $requeue Requeue flag
     * @return void
     */
    public function reject(Message $message, bool $requeue = false): void
    {
    }
}
