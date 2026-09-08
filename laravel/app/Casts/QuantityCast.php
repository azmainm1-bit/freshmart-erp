<?php

namespace App\Casts;

use App\Support\Decimal\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Quantity|null, Quantity|string|null> */
class QuantityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        return $value === null ? null : Quantity::of((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Quantity::of($value)->toString();
    }
}
