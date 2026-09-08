<?php

namespace App\Exceptions;

class InsufficientStockException extends BusinessRuleException
{
    public function __construct(string $productId, string $available, string $requested)
    {
        parent::__construct(
            409,
            'INSUFFICIENT_STOCK',
            "Insufficient stock for product {$productId}",
            ['productId' => $productId, 'available' => $available, 'requested' => $requested]
        );
    }
}
