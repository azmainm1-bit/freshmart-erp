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
 * @property Carbon|null $posted_at
 */
class CashMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['shift_id', 'actor_id', 'type', 'amount', 'reason', 'posted_at'];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class, 'posted_at' => 'datetime'];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
