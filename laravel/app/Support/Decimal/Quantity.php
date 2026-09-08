<?php

namespace App\Support\Decimal;

use Brick\Math\RoundingMode;

/** Stock/sale quantity, 3 decimal places — supports weighted items (e.g. 0.750 kg). */
final class Quantity extends AbstractDecimal
{
    protected static function scale(): int
    {
        return 3;
    }

    public function isInteger(): bool
    {
        return $this->toBigDecimal()->isEqualTo($this->toBigDecimal()->toScale(0, RoundingMode::Down));
    }
}
