<?php
declare(strict_types=1);

namespace TestApp\Dto;

class UserDto
{
    /**
     * @param array<string, mixed> $data
     */
    public static function createFromArray(array $data, bool $nested = false): static
    {
        return new static($data['id'], $data['username']);
    }

    public function __construct(
        public int $id,
        public string $username,
    ) {
    }
}
