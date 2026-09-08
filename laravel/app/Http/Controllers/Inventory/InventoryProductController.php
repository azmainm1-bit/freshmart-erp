<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventoryProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        Gate::authorize('inventory.view');
        $cost = $request->user()->can('products.view-cost');
        $balances = $product->stockBalances()->with('location:id,name,type', 'batch:id,batch_no,expiry_date')->get();
        $movements = $product->stockMovements()->with('location:id,name', 'batch:id,batch_no', 'actor:id,name')->orderByDesc('created_at')->paginate(30);
        if (! $cost) {
            $balances->each(fn ($balance) => $balance->makeHidden('average_cost'));
            $movements->through(fn ($movement) => $movement->makeHidden('unit_cost'));
        }

        return Inertia::render('inventory/product', ['product' => $product, 'balances' => $balances, 'movements' => $movements, 'showCost' => $cost]);
    }

    public function options(Product $product)
    {
        Gate::authorize('inventory.view');

        return response()->json(['balances' => $product->stockBalances()->select('id', 'product_id', 'location_id', 'batch_id', 'quantity_on_hand')->with('batch:id,batch_no,expiry_date')->orderBy('location_id')->get()]);
    }
}
