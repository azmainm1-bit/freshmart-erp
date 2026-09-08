<?php

namespace App\Support\Sales;

use App\Models\Product;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class SalePricing
{
    public static function line(Product $product, Quantity $quantity, Money $discount): array
    {
        $gross = $product->selling_price->multipliedBy($quantity->toBigDecimal());
        BusinessRules::require(! $discount->isNegative() && $discount->isLessThanOrEqualTo($gross), 'discount_amount', 'Discount must be between zero and the line value.');
        $discounted = $gross->minus($discount);
        $rate = BigDecimal::of((string) $product->tax_rate_percent)->multipliedBy('0.01');
        if ($product->tax_inclusive) {
            $tax = Money::of($discounted->toBigDecimal()->minus($discounted->toBigDecimal()->dividedBy($rate->plus(1), 12, RoundingMode::HalfUp)));
            $subtotal = $discounted->minus($tax);
        } else {
            $subtotal = $discounted;
            $tax = Money::of($subtotal->toBigDecimal()->multipliedBy($rate));
        }

        return ['unit_price' => $product->selling_price, 'discount_amount' => $discount, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'line_total' => $subtotal->plus($tax)];
    }
}
