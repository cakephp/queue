<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\StampInterface;

final class SecondStamp implements StampInterface
{
    public function toArray(): array
    {
        return [
            'type' => 'SecondStamp',
            'v' => 1,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }
}
