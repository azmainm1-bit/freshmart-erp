<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('customers.manage');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Customer::query()->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$request->input('q').'%')->orWhere('phone', 'ilike', '%'.$request->input('q').'%')));
        if ($request->user()->can('finance.manage')) {
            $query->select('customers.*')->selectSub(DB::table('sales')->whereColumn('customer_id', 'customers.id')->selectRaw('coalesce(sum(grand_total - returned_amount - paid_amount + refunded_amount),0)'), 'balance');
        }

        return Inertia::render('customers/index', ['customers' => $query->orderBy('name')->paginate(20)->withQueryString(), 'filters' => ['q' => $request->input('q', '')]]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('customers.manage');
        $sales = Sale::where('customer_id', $customer->id)->when(! $request->user()->can('sales.view-all'), fn ($q) => $q->where('cashier_id', $request->user()->id))->orderByDesc('posted_at')->paginate(20);
        $balance = $request->user()->can('finance.manage') ? (string) DB::table('sales')->where('customer_id', $customer->id)->selectRaw('coalesce(sum(grand_total - returned_amount - paid_amount + refunded_amount),0)::text as total')->first()->total : null;

        return Inertia::render('customers/show', ['customer' => $customer, 'sales' => $sales, 'balance' => $balance]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $customer = DB::transaction(function () use ($request) {
            $customer = Customer::create($request->validated());
            AuditLog::record($request->user(), 'CUSTOMER_CREATED', $customer);

            return $customer;
        });

        return redirect('/customers/'.$customer->id)->with('success', 'Customer created.');
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        DB::transaction(function () use ($request, $customer) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $customer->update($request->validated());
            AuditLog::record($request->user(), 'CUSTOMER_UPDATED', $customer);
        });

        return back()->with('success', 'Customer updated.');
    }
}
