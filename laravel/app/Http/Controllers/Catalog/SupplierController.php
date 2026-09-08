<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreSupplierRequest;
use App\Http\Requests\Catalog\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('suppliers.manage');

        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = $request->string('q')->toString();
        $showInactive = $request->boolean('inactive');

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->where('active', ! $showInactive)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('catalog/suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $search, 'inactive' => $showInactive],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return back()->with('success', 'Supplier created.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier updated.');
    }
}
