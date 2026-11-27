<?php
declare(strict_types=1);

namespace TestApp\Bus;

class NoArgRecordingMiddleware extends RecordingMiddleware
{
    /**
     * @param array<string> $calls
     */
    public function __construct(array &$calls = [])
    {
        parent::__construct('stub', $calls);
    }
}
