<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\TransactionRequest;

class StoreExpenseRequest extends TransactionRequest
{
    protected string $permission = 'finance.manage';

    protected function fields(): array
    {
        return [
            'expense_category_id' => ['required', 'uuid', 'exists:expense_categories,id'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'amount' => $this->money(true), 'method' => ['required', 'in:cash,card,mobile,bank'],
            'reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:255'],
            'paid_from' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
