<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Decimal\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Money $credit_limit
 */
class Customer extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'phone', 'email', 'address', 'notes', 'credit_limit', 'active'];

    protected function casts(): array
    {
        return ['credit_limit' => MoneyCast::class, 'active' => 'boolean'];
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
