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
 * @property Carbon|null $expense_date
 */
class Expense extends Model
{
    use HasUuids;

    protected $fillable = ['number', 'expense_category_id', 'actor_id', 'location_id', 'amount', 'method', 'reference', 'paid_from', 'expense_date', 'description'];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class, 'expense_date' => 'date'];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
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
}
