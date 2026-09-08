<?php

namespace App\Support\Decimal;

/**
 * Unit cost / moving-weighted-average cost, 6 decimal places — extra
 * precision so carton->unit division and WAC blending don't lose accuracy
 * before a value is rounded into a Money field (docs/ARCHITECTURE.md).
 */
final class Cost extends AbstractDecimal
{
    protected static function scale(): int
    {
        return 6;
    }
}
