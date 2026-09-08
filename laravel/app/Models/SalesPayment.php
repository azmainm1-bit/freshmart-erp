<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Decimal\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Money $amount
 * @property Carbon|null $paid_at
 */
class SalesPayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['sale_id', 'sales_return_id', 'shift_id', 'actor_id', 'kind', 'method', 'amount', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class, 'paid_at' => 'datetime'];
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

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
