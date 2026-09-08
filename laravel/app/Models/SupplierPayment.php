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
class SupplierPayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'actor_id', 'amount', 'method', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class, 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
