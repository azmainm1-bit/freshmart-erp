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
 * @property Quantity $quantity
 * @property Quantity $received_quantity
 * @property Cost $unit_cost
 * @property Money $line_total
 */
class PurchaseOrderLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'received_quantity', 'unit_cost', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => QuantityCast::class, 'received_quantity' => QuantityCast::class, 'unit_cost' => CostCast::class, 'line_total' => MoneyCast::class];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
