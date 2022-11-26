<?php
declare(strict_types=1);

namespace Cake\Queue\Test\test_app\src\Job;

use Cake\Log\Log;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;
use TestApp\TestService;

class LogToDebugWithServiceJob implements JobInterface
{
    /**
     * @var \TestApp\TestService $testService
     */
    private $testService;

    public function __construct(TestService $testService)
    {
        $this->testService = $testService;
    }

    public function execute(Message $message): ?string
    {
        Log::debug('Debug job was run ' . $this->testService->info());

        return Processor::ACK;
    }
}
