<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('products.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'selling_price' => ['required', 'numeric', 'gte:0'],
            'tax_rate_percent' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'tax_inclusive' => ['required', 'boolean'],
            'reorder_point' => ['required', 'numeric', 'gte:0'],
            'default_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'archived' => ['required', 'boolean'],
        ];
    }
}
