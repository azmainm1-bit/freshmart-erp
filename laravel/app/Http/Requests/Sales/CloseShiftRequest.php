<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class CloseShiftRequest extends TransactionRequest
{
    protected string $permission = 'sales.create';

    protected function fields(): array
    {
        return [
            'counted_cash' => $this->money(), 'closing_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
