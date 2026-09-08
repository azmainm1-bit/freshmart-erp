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
 * @property Quantity $quantity
 * @property Cost $unit_cost
 * @property Carbon|null $posted_at
 */
class StockOperation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['number', 'type', 'product_id', 'location_id', 'destination_id', 'batch_id', 'actor_id', 'quantity', 'unit_cost', 'reason', 'posted_at'];

    protected function casts(): array
    {
        return ['quantity' => QuantityCast::class, 'unit_cost' => CostCast::class, 'posted_at' => 'datetime'];
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

    /** @return BelongsTo<Location, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
