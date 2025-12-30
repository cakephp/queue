<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\Queue;
use Interop\Queue\Topic;

/**
 * Test Destination
 *
 * Implements both Queue and Topic interfaces for testing.
 */
class TestDestination implements Queue, Topic
{
    /**
     * Destination name
     *
     * @var string
     */
    protected string $name;

    /**
     * Constructor
     *
     * @param string $name Destination name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get queue name
     *
     * @return string
     */
    public function getQueueName(): string
    {
        return $this->name;
    }

    /**
     * Get topic name
     *
     * @return string
     */
    public function getTopicName(): string
    {
        return $this->name;
    }

    /**
     * Get destination name (for compatibility)
     *
     * @return string
     */
    public function getDestinationName(): string
    {
        return $this->name;
    }
}
