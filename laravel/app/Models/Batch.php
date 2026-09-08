<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expiry_date
 */
class Batch extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'batch_no', 'expiry_date'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
