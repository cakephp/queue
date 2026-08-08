<?php
declare(strict_types=1);

namespace TestApp\Dto;

use Cake\ORM\Attribute\CollectionOf;

readonly class OrderDto
{
    /**
     * @param array<int, \TestApp\Dto\OrderItemDto> $items
     */
    public function __construct(
        public int $id,
        public string $customer,
        #[CollectionOf(OrderItemDto::class)]
        public array $items = [],
    ) {
    }
}
