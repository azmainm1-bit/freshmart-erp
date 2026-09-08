<?php

namespace App\Models;

use App\Casts\CostCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Quantity $quantity_on_hand
 * @property Cost $average_cost
 * @property Carbon|null $updated_at
 */
class StockBalance extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['product_id', 'location_id', 'batch_id', 'quantity_on_hand', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => QuantityCast::class,
            'average_cost' => CostCast::class,
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
