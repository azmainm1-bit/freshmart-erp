<?php

namespace App\Casts;

use App\Support\Decimal\Cost;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Cost|null, Cost|string|null> */
class CostCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Cost
    {
        return $value === null ? null : Cost::of((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Cost::of($value)->toString();
    }
}
