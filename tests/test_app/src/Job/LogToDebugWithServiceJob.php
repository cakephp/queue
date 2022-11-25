<?php
declare(strict_types=1);

namespace Cake\Queue\Test\test_app\src\Job;

use Cake\Log\Log;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Cake\Queue\Queue\ServicesTrait;
use Interop\Queue\Processor;
use TestApp\TestService;

class LogToDebugWithServiceJob implements JobInterface
{
    use ServicesTrait;

    public function execute(Message $message): ?string
    {
        /** @var TestService $service */
        $service = $this->getService(TestService::class);
        Log::debug('Debug job was run ' . $service->info());

        return Processor::ACK;
    }
}
