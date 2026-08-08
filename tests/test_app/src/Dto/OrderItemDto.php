<?php
declare(strict_types=1);

namespace TestApp\Dto;

readonly class OrderItemDto
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {
    }
}
