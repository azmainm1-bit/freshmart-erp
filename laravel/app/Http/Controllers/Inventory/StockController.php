<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function show(Product $product): Response
    {
        Gate::authorize('inventory.view');

        return Inertia::render('inventory/stock/show', [
            'product' => $product,
            'balances' => $product->stockBalances()->with('location:id,name,type', 'batch:id,batch_no,expiry_date')->get(),
            'movements' => $product->stockMovements()
                ->with('location:id,name', 'batch:id,batch_no', 'actor:id,name,username')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }
}
