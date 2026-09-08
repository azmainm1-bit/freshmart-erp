<?php

namespace App\Support;

use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Brick\Math\RoundingMode;

final class Amounts
{
    public static function proportion(Money $amount, Quantity $part, Quantity $whole): Money
    {
        return Money::of($amount->toBigDecimal()->multipliedBy($part->toBigDecimal())->dividedBy($whole->toBigDecimal(), 12, RoundingMode::HalfUp));
    }
}
