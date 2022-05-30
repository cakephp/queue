<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase;

use Cake\Log\Log;

trait DebugLogTrait
{
    protected function assertDebugLogContains($expected, $times = null): void
    {
        $found = $this->debugLogCount($expected);

        $this->assertGreaterThanOrEqual(1, $found, "Did not find `{$expected}` in logs.");
    }

    protected function assertDebugLogContainsExactly($expected, $times): void
    {
        $found = $this->debugLogCount($expected);

        $this->assertSame($times, $found, "Did not find `{$expected}` exactly {$times} times in logs.");
    }

    protected function debugLogCount($seach)
    {
        $log = Log::engine('debug');
        $found = 0;
        foreach ($log->read() as $line) {
            if (strpos($line, $seach) !== false) {
                $found++;
            }
        }

        return $found;
    }
}
