<?php
declare(strict_types=1);

namespace TestApp\Bus;

class TestMessage
{
    public function __construct(
        public readonly string $text = 'hello',
    ) {
    }
}
