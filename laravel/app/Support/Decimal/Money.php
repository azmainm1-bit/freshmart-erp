<?php

namespace App\Support\Decimal;

/** BDT money, 2 decimal places (paisa) — docs/ARCHITECTURE.md */
final class Money extends AbstractDecimal
{
    protected static function scale(): int
    {
        return 2;
    }
}
