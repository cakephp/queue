<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class ScalarResultJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return Processor::ACK;
    }

    /**
     * Non-standard entry point returning a scalar.
     *
     * Dispatchable via `[$class, 'run']`; used to prove scalar results
     * are rejected instead of crashing the worker with a `TypeError`.
     */
    public function run(Message $message): mixed
    {
        return true;
    }
}
