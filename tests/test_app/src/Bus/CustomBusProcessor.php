<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;

class CustomBusProcessor implements InteropProcessor
{
    public bool $processed = false;

    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->processed = true;

        return 'custom';
    }
}
