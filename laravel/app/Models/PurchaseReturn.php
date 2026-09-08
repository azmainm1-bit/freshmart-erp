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
 * @property Carbon|null $posted_at
 */
class PurchaseReturn extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['number', 'goods_receipt_id', 'actor_id', 'total_amount', 'reason', 'posted_at'];

    protected function casts(): array
    {
        return ['total_amount' => MoneyCast::class, 'posted_at' => 'datetime'];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
