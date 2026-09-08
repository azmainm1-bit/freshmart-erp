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
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $discount_total
 * @property Money $grand_total
 * @property Money $cost_total
 * @property Money $paid_amount
 * @property Money $returned_amount
 * @property Money $refunded_amount
 * @property Money $tendered_amount
 * @property Money $change_amount
 * @property Carbon|null $posted_at
 */
class Sale extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['number', 'shift_id', 'location_id', 'cashier_id', 'customer_id', 'subtotal', 'tax_total', 'discount_total', 'grand_total', 'cost_total', 'paid_amount', 'returned_amount', 'refunded_amount', 'tendered_amount', 'change_amount', 'notes', 'posted_at'];

    protected $hidden = ['cost_total'];

    protected function casts(): array
    {
        return ['subtotal' => MoneyCast::class, 'tax_total' => MoneyCast::class, 'discount_total' => MoneyCast::class, 'grand_total' => MoneyCast::class, 'cost_total' => MoneyCast::class, 'paid_amount' => MoneyCast::class, 'returned_amount' => MoneyCast::class, 'refunded_amount' => MoneyCast::class, 'tendered_amount' => MoneyCast::class, 'change_amount' => MoneyCast::class, 'posted_at' => 'datetime'];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /** @return HasMany<SalesPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class);
    }

    /** @return HasMany<SalesReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }
}
