<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Decimal\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Money $total_amount
 * @property Money $paid_amount
 * @property Money $returned_amount
 * @property Carbon|null $posted_at
 */
class GoodsReceipt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['supplier_id', 'location_id', 'purchase_order_id', 'received_by_id', 'posted_at', 'number', 'supplier_reference', 'total_amount', 'paid_amount', 'returned_amount', 'notes'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'total_amount' => MoneyCast::class, 'paid_amount' => MoneyCast::class, 'returned_amount' => MoneyCast::class];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** @return HasMany<PurchaseReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
