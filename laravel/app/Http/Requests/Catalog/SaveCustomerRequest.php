<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'], 'active' => ['sometimes', 'boolean'],
            'credit_limit' => $this->user()->can('finance.manage') ? ['sometimes', 'string', 'numeric', 'decimal:0,2', 'gte:0', 'max:999999999999.99'] : ['prohibited'],
        ];
    }
}
