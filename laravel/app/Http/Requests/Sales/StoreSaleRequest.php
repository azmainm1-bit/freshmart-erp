<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class StoreSaleRequest extends TransactionRequest
{
    protected string $permission = 'sales.create';

    protected function fields(): array
    {
        return [
            'shift_id' => ['required', 'uuid', 'exists:shifts,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expected_total' => ['nullable', 'string', 'numeric', 'decimal:0,2', 'gte:0'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => $this->quantity(),
            'lines.*.discount_amount' => ['nullable', 'string', 'numeric', 'decimal:0,2', 'gte:0', 'max:999999999999.99'],
            'payments' => ['present', 'array', 'max:5'],
            'payments.*.method' => ['required', 'in:cash,card,mobile,bank'],
            'payments.*.amount' => $this->money(true),
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
