<?php
declare(strict_types=1);

namespace Cake\Queue\Consumption;

use Enqueue\Consumption\Context\PostConsume;
use Enqueue\Consumption\Context\PreConsume;
use Enqueue\Consumption\PostConsumeExtensionInterface;
use Enqueue\Consumption\PreConsumeExtensionInterface;
use Psr\Log\LoggerInterface;

/**
 * A consumer extension to limit the number of messages that are processed.
 *
 * This is a place holder until the upstream enqueue project extension is merged.
 *
 * @see https://github.com/php-enqueue/enqueue-dev/pull/1244
 */
class LimitConsumedMessagesExtension implements PreConsumeExtensionInterface, PostConsumeExtensionInterface
{
    /**
     * @var int
     */
    protected $messageLimit;

    /**
     * @var int
     */
    protected $messageConsumed = 0;

    /**
     * @param int $messageLimit The number of messages to process before exiting.
     */
    public function __construct(int $messageLimit)
    {
        $this->messageLimit = $messageLimit;
    }

    /**
     * Executed at every new cycle before calling SubscriptionConsumer::consume method.
     * The consumption could be interrupted at this step.
     *
     * @param \Enqueue\Consumption\Context\PreConsume $context The PreConsume context.
     * @return void
     */
    public function onPreConsume(PreConsume $context): void
    {
        // this is added here to handle an edge case. when a user sets zero as limit.
        if ($this->shouldBeStopped($context->getLogger())) {
            $context->interruptExecution();
        }
    }

    /**
     * The method is called after SubscriptionConsumer::consume method exits.
     * The consumption could be interrupted at this point.
     *
     * @param \Enqueue\Consumption\Context\PostConsume $context The PostConsume context.
     * @return void
     */
    public function onPostConsume(PostConsume $context): void
    {
        ++$this->messageConsumed;

        if ($this->shouldBeStopped($context->getLogger())) {
            $context->interruptExecution();
        }
    }

    /**
     * Check if the consumer should be stopped.
     *
     * @param \Psr\Log\LoggerInterface $logger The logger where messages will be logged.
     * @return bool
     */
    protected function shouldBeStopped(LoggerInterface $logger): bool
    {
        if ($this->messageConsumed >= $this->messageLimit) {
            $logger->debug(sprintf(
                '[LimitConsumedMessagesExtension] Message consumption is interrupted since the message limit ' .
                'reached. limit: "%s"',
                $this->messageLimit
            ));

            return true;
        }

        return false;
    }
}
