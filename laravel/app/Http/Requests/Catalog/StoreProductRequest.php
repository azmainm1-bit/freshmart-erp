<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('products.manage');
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'stock_unit' => ['required', 'string', 'max:50'],
            'purchase_unit' => ['required', 'string', 'max:50'],
            'pack_conversion_factor' => ['required', 'numeric', 'gt:0'],
            'selling_price' => ['required', 'numeric', 'gte:0'],
            'tax_rate_percent' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'tax_inclusive' => ['required', 'boolean'],
            'is_weighted' => ['required', 'boolean'],
            'is_batch_tracked' => ['required', 'boolean'],
            'reorder_point' => ['required', 'numeric', 'gte:0'],
            'default_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'barcodes' => ['array'],
            'barcodes.*' => ['string', 'max:100', 'distinct', 'unique:barcodes,code'],
        ];
    }
}
