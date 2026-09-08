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
 * @property Money $total_amount
 * @property Money $tax_amount
 * @property Money $cost_amount
 */
class SalesReturnLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['sales_return_id', 'sale_line_id', 'quantity', 'total_amount', 'tax_amount', 'cost_amount'];

    protected $hidden = ['cost_amount'];

    protected function casts(): array
    {
        return ['quantity' => QuantityCast::class, 'total_amount' => MoneyCast::class, 'tax_amount' => MoneyCast::class, 'cost_amount' => MoneyCast::class];
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}
