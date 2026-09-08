<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\TransactionRequest;

class StoreSupplierPaymentRequest extends TransactionRequest
{
    protected string $permission = 'finance.manage';

    protected function fields(): array
    {
        return ['amount' => $this->money(true), 'method' => ['required', 'in:cash,bank,card,mobile'], 'reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:255']];
    }
}
