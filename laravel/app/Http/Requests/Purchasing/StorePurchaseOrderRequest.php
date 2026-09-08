<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\TransactionRequest;

class StorePurchaseOrderRequest extends TransactionRequest
{
    protected string $permission = 'purchasing.manage';

    protected function fields(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => $this->quantity(),
            'lines.*.unit_cost' => ['required', 'string', 'numeric', 'decimal:0,6', 'gte:0', 'max:99999999.999999'],
        ];
    }
}
