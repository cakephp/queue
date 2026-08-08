<?php
declare(strict_types=1);

namespace TestApp\Dto;

use JsonSerializable;

class ScalarJsonSerializableDto implements JsonSerializable
{
    public function __construct(
        public string $value,
    ) {
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
