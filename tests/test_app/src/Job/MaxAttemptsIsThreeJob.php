<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Log\Log;
use Cake\Queue\Consumption\LimitAttemptsExtension;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;
use RuntimeException;

class MaxAttemptsIsThreeJob implements JobInterface
{
    public static $maxAttempts = 3;

    public function execute(Message $message): ?string
    {
        $succeedAt = $message->getArgument('succeedAt');

        $originalMessage = $message->getOriginalMessage();
        $attemptNumber = $originalMessage->getProperty(LimitAttemptsExtension::ATTEMPTS_PROPERTY) + 1;

        if (!$succeedAt || $succeedAt > $attemptNumber) {
            Log::debug('MaxAttemptsIsThreeJob is requeueing');

            throw new RuntimeException('example MaxAttemptsIsThreeJob exception message');
        }

        Log::debug('MaxAttemptsIsThreeJob was run');

        return Processor::ACK;
    }
}
