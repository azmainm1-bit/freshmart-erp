<?php

namespace App\Models;

use App\Casts\CostCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Quantity $quantity
 * @property Quantity $returned_quantity
 * @property Cost $unit_cost
 */
class SaleAllocation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['sale_line_id', 'batch_id', 'quantity', 'returned_quantity', 'unit_cost'];

    protected $hidden = ['unit_cost'];

    protected function casts(): array
    {
        return ['quantity' => QuantityCast::class, 'returned_quantity' => QuantityCast::class, 'unit_cost' => CostCast::class];
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
