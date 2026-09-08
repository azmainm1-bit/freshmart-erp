<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\TransactionRequest;

class StorePurchaseReturnRequest extends TransactionRequest
{
    protected string $permission = 'purchasing.manage';

    protected function fields(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.goods_receipt_line_id' => ['required', 'uuid', 'distinct', 'exists:goods_receipt_lines,id'],
            'lines.*.quantity' => $this->quantity(),
        ];
    }
}
