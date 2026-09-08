<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Money $selling_price
 * @property Quantity $reorder_point
 */
class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sku', 'name', 'name_bn', 'category', 'brand',
        'stock_unit', 'purchase_unit', 'pack_conversion_factor',
        'selling_price', 'tax_rate_percent', 'tax_inclusive',
        'is_weighted', 'is_batch_tracked', 'reorder_point',
        'default_supplier_id', 'archived',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => MoneyCast::class,
            'reorder_point' => QuantityCast::class,
            'tax_inclusive' => 'boolean',
            'is_weighted' => 'boolean',
            'is_batch_tracked' => 'boolean',
            'archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    /** @return HasMany<Barcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    /** @return HasMany<Batch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /** @return HasMany<StockBalance, $this> */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
