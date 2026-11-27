<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\StampInterface;

final class AnotherStamp implements StampInterface
{
    public function toArray(): array
    {
        return [
            'type' => 'AnotherStamp',
            'v' => 1,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }
}
