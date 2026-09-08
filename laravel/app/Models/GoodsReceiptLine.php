<?php

namespace App\Models;

use App\Casts\CostCast;
use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Quantity $quantity_received
 * @property Quantity $returned_quantity
 * @property Cost $unit_cost
 * @property Money $line_total
 */
class GoodsReceiptLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'product_id', 'batch_id', 'quantity_received', 'unit_cost', 'line_total', 'returned_quantity'];

    protected function casts(): array
    {
        return [
            'quantity_received' => QuantityCast::class,
            'returned_quantity' => QuantityCast::class,
            'unit_cost' => CostCast::class,
            'line_total' => MoneyCast::class,
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
