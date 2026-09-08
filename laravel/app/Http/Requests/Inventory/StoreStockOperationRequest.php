<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\TransactionRequest;

class StoreStockOperationRequest extends TransactionRequest
{
    protected string $permission = 'inventory.adjust';

    public function authorize(): bool
    {
        return $this->user()?->can($this->input('type') === 'transfer' ? 'inventory.transfer' : $this->permission) ?? false;
    }

    protected function fields(): array
    {
        return [
            'type' => ['required', 'in:adjustment,damage,expired,transfer'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'destination_id' => ['nullable', 'required_if:type,transfer', 'uuid', 'different:location_id', 'exists:locations,id'],
            'batch_id' => ['nullable', 'uuid', 'exists:batches,id'],
            'quantity' => ['required', 'string', 'numeric', 'decimal:0,3', 'not_in:0', 'between:-999999999.999,999999999.999'],
            'unit_cost' => ['nullable', 'string', 'numeric', 'decimal:0,6', 'gte:0', 'max:99999999.999999'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
