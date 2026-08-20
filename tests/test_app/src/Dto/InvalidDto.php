<?php
declare(strict_types=1);

namespace TestApp\Dto;

class InvalidDto
{
    public function __construct(
        public string $required,
    ) {
    }
}
