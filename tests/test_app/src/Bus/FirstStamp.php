<?php
declare(strict_types=1);

namespace TestApp\Bus;

final class FirstStamp implements StampGroupInterface
{
    public function toArray(): array
    {
        return [
            'type' => 'FirstStamp',
            'v' => 1,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }
}
