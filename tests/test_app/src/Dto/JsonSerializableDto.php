<?php
declare(strict_types=1);

namespace TestApp\Dto;

use JsonSerializable;

class JsonSerializableDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => strtoupper($this->name),
        ];
    }
}
