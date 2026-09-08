<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class StoreSalesReturnRequest extends TransactionRequest
{
    protected string $permission = 'sales.return';

    protected function fields(): array
    {
        return [
            'shift_id' => ['nullable', 'uuid', 'exists:shifts,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'disposition' => ['required', 'in:restock,quarantine'],
            'refund_method' => ['required', 'in:cash,card,mobile,bank'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.sale_line_id' => ['required', 'uuid', 'distinct', 'exists:sale_lines,id'],
            'lines.*.quantity' => $this->quantity(),
        ];
    }
}
