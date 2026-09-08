<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class StoreCashMovementRequest extends TransactionRequest
{
    protected string $permission = 'sales.create';

    protected function fields(): array
    {
        return [
            'type' => ['required', 'in:in,out'], 'amount' => $this->money(true), 'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
