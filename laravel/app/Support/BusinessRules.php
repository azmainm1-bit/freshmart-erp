<?php

namespace App\Support;

use App\Models\Product;
use App\Support\Decimal\Quantity;
use Illuminate\Validation\ValidationException;

final class BusinessRules
{
    public static function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public static function quantity(Product $product, string $value, string $field = 'quantity'): Quantity
    {
        $quantity = Quantity::of($value);
        self::require($quantity->isPositive(), $field, 'Quantity must be greater than zero.');
        self::require($product->is_weighted || $quantity->isInteger(), $field, $product->name.' requires a whole number of units.');

        return $quantity;
    }
}
