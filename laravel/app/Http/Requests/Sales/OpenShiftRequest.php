<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\TransactionRequest;

class OpenShiftRequest extends TransactionRequest
{
    protected string $permission = 'sales.create';

    protected function fields(): array
    {
        return [
            'location_id' => ['required', 'uuid', 'exists:locations,id'], 'counter' => ['required', 'string', 'max:80'], 'opening_cash' => $this->money(),
        ];
    }
}
