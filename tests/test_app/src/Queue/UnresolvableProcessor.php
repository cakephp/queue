<?php
declare(strict_types=1);

namespace TestApp\Queue;

use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;

/**
 * Custom processor that cannot be built with `(logger, container)`.
 */
class UnresolvableProcessor implements InteropProcessor
{
    public function __construct(
        protected string $mandatoryOne,
        protected string $mandatoryTwo,
        protected string $mandatoryThree,
    ) {
    }

    public function process(QueueMessage $message, Context $context): string|object
    {
        return InteropProcessor::ACK;
    }
}
