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
 * @property Money $tax_amount
 * @property Money $cost_amount
 * @property Money $refund_amount
 * @property Carbon|null $posted_at
 */
class SalesReturn extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['number', 'sale_id', 'actor_id', 'location_id', 'total_amount', 'tax_amount', 'cost_amount', 'refund_amount', 'reason', 'disposition', 'posted_at'];

    protected $hidden = ['cost_amount'];

    protected function casts(): array
    {
        return ['total_amount' => MoneyCast::class, 'tax_amount' => MoneyCast::class, 'cost_amount' => MoneyCast::class, 'refund_amount' => MoneyCast::class, 'posted_at' => 'datetime'];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<SalesReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }
}
