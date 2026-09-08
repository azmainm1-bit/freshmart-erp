<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('products.manage');

        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:255']]);
        $search = $request->string('q')->toString();
        $showArchived = $request->boolean('archived');

        $products = Product::query()
            ->with('barcodes:id,product_id,code', 'defaultSupplier:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('name_bn', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%")
                        ->orWhereHas('barcodes', fn ($b) => $b->where('code', $search));
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->where('archived', $showArchived)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('catalog/products/index', [
            'products' => $products,
            'filters' => ['q' => $search, 'archived' => $showArchived],
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $barcodes = $data['barcodes'] ?? [];
        unset($data['barcodes']);

        DB::transaction(function () use ($data, $barcodes) {
            $product = Product::create($data);
            foreach ($barcodes as $code) {
                $product->barcodes()->create(['code' => $code]);
            }
        });

        return back()->with('success', 'Product created.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Product updated.');
    }
}
