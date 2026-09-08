<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inventory.receive');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'purchase_order_id' => ['nullable', 'uuid', 'exists:purchase_orders,id'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.quantity_received' => ['required', 'string', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'lines.*.unit_cost' => ['required', 'string', 'numeric', 'decimal:0,6', 'gte:0', 'max:99999999.999999'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:255'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
