<?php

namespace App\Support\Inventory;

use App\Models\StockBalance;
use App\Models\StockMovement;

final readonly class StockMovementResult
{
    public function __construct(
        public StockMovement $movement,
        public StockBalance $balance,
    ) {}
}
