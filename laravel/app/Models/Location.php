<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasUuids;

    public const TYPE_STOCKROOM = 'stockroom';

    public const TYPE_SALES_FLOOR = 'sales_floor';

    public const TYPE_QUARANTINE = 'quarantine';

    protected $fillable = ['name', 'type'];
}
