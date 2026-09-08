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
 * @property Quantity $quantity_delta
 * @property Cost $unit_cost
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    use HasUuids;

    public const OPENING_BALANCE = 'opening_balance';

    public const GOODS_RECEIPT = 'goods_receipt';

    public const SALE = 'sale';

    public const RETURN = 'return';

    public const ADJUSTMENT = 'adjustment';

    public const WRITE_OFF = 'write_off';

    public const PURCHASE_RETURN = 'purchase_return';

    public const TRANSFER_OUT = 'transfer_out';

    public const TRANSFER_IN = 'transfer_in';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'location_id', 'batch_id', 'quantity_delta', 'unit_cost',
        'movement_type', 'source_document_type', 'source_document_id',
        'actor_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => QuantityCast::class,
            'unit_cost' => CostCast::class,
            'created_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
