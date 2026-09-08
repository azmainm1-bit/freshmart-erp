<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Quantity $quantity
 * @property Money $amount
 */
class PurchaseReturnLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['purchase_return_id', 'goods_receipt_line_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => QuantityCast::class, 'amount' => MoneyCast::class];
    }

    /** @return BelongsTo<GoodsReceiptLine, $this> */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }
}
