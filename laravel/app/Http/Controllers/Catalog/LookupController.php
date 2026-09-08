<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Support\BusinessDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LookupController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        Gate::authorize('inventory.view');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'location_id' => ['nullable', 'uuid', 'exists:locations,id'], 'category' => ['nullable', 'string', 'max:255']]);
        $search = $data['q'] ?? '';
        $products = Product::where('archived', false)->with('barcodes:id,product_id,code')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('name_bn', 'ilike', "%{$search}%")->orWhere('sku', 'ilike', "%{$search}%")->orWhereHas('barcodes', fn ($b) => $b->where('code', $search))))
            ->when(! empty($data['category']), fn ($q) => $q->where('category', $data['category']))
            ->withSum(['stockBalances as available' => fn ($q) => $q->when(! empty($data['location_id']), fn ($q) => $q->where('location_id', $data['location_id']))->whereHas('location', fn ($l) => $l->where('type', '<>', 'quarantine'))->where(fn ($q) => $q->whereNull('batch_id')->orWhereHas('batch', fn ($b) => $b->whereNull('expiry_date')->orWhere('expiry_date', '>=', BusinessDate::today())))], 'quantity_on_hand')
            ->orderBy('name')->limit(40)->get();

        return response()->json(['products' => $products]);
    }

    public function customers(Request $request): JsonResponse
    {
        Gate::authorize('customers.manage');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = $request->input('q', '');

        return response()->json(['customers' => Customer::where('active', true)->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%"))->orderBy('name')->limit(20)->get(['id', 'name', 'phone'])]);
    }
}
