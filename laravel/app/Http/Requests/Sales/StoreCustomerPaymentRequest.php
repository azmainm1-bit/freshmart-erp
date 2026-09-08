<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class StoreCustomerPaymentRequest extends TransactionRequest
{
    protected string $permission = 'finance.manage';

    protected function fields(): array
    {
        return [
            'shift_id' => ['nullable', 'uuid', 'exists:shifts,id'], 'amount' => $this->money(true), 'method' => ['required', 'in:cash,card,mobile,bank'], 'reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:255'],
        ];
    }
}
