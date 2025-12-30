<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\Consumer;
use Interop\Queue\SubscriptionConsumer;

/**
 * Test Subscription Consumer
 *
 * Minimal subscription consumer implementation for testing.
 */
class TestSubscriptionConsumer implements SubscriptionConsumer
{
    /**
     * Consume
     *
     * @param int $timeout Timeout in milliseconds
     * @return void
     */
    public function consume(int $timeout = 0): void
    {
    }

    /**
     * Subscribe
     *
     * @param \Interop\Queue\Consumer $consumer Consumer
     * @param callable $callback Callback
     * @return void
     */
    public function subscribe(Consumer $consumer, callable $callback): void
    {
    }

    /**
     * Unsubscribe
     *
     * @param \Interop\Queue\Consumer $consumer Consumer
     * @return void
     */
    public function unsubscribe(Consumer $consumer): void
    {
    }

    /**
     * Unsubscribe all
     *
     * @return void
     */
    public function unsubscribeAll(): void
    {
    }
}
