<?php
declare(strict_types=1);

namespace TestApp\Bus;

use Cake\Queue\Bus\StampInterface;
use InvalidArgumentException;

/**
 * App-defined stamp used to test the stamp type registry.
 *
 * Core knows nothing about tenants: the type, the payload and the meaning
 * all belong to the application. Core only carries the serialized row and
 * rebuilds it through the registered map.
 */
final class TenantStamp implements StampInterface
{
    public function __construct(
        private readonly string $tenant,
    ) {
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }

    public function toArray(): array
    {
        return [
            'type' => 'TenantStamp',
            'v' => 1,
            'tenant' => $this->tenant,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['tenant']) || !is_string($data['tenant']) || $data['tenant'] === '') {
            throw new InvalidArgumentException('TenantStamp requires a non-empty string `tenant`.');
        }

        return new self($data['tenant']);
    }
}
