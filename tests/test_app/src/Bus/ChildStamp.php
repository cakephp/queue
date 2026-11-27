<?php
declare(strict_types=1);

namespace TestApp\Bus;

final class ChildStamp implements StampGroupInterface
{
    public function toArray(): array
    {
        return [
            'type' => 'ChildStamp',
            'v' => 1,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }
}
