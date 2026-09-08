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
 * @property Money $opening_cash
 * @property Money|null $counted_cash
 * @property Money|null $expected_cash
 * @property Money|null $variance
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 */
class Shift extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['cashier_id', 'location_id', 'counter', 'status', 'opening_cash', 'counted_cash', 'expected_cash', 'variance', 'closing_note', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return ['opening_cash' => MoneyCast::class, 'counted_cash' => MoneyCast::class, 'expected_cash' => MoneyCast::class, 'variance' => MoneyCast::class, 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<SalesPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class);
    }

    /** @return HasMany<CashMovement, $this> */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
