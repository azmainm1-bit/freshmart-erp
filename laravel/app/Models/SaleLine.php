<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Money $unit_price
 * @property Money $discount_amount
 * @property Money $subtotal
 * @property Money $tax_amount
 * @property Money $line_total
 * @property Money $cost_total
 * @property Quantity $quantity
 * @property Quantity $returned_quantity
 */
class SaleLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['sale_id', 'product_id', 'product_name', 'sku', 'stock_unit', 'quantity', 'returned_quantity', 'unit_price', 'tax_rate_percent', 'tax_inclusive', 'discount_amount', 'subtotal', 'tax_amount', 'line_total', 'cost_total'];

    protected $hidden = ['cost_total'];

    protected function casts(): array
    {
        return ['unit_price' => MoneyCast::class, 'discount_amount' => MoneyCast::class, 'subtotal' => MoneyCast::class, 'tax_amount' => MoneyCast::class, 'line_total' => MoneyCast::class, 'cost_total' => MoneyCast::class, 'quantity' => QuantityCast::class, 'returned_quantity' => QuantityCast::class, 'tax_inclusive' => 'boolean'];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<SaleAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SaleAllocation::class);
    }
}
