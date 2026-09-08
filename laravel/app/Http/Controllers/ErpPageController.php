<?php

namespace App\Http\Controllers;

use App\Models\AuditLogEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Shift;
use App\Models\StockOperation;
use App\Models\Supplier;
use App\Support\AuditLog;
use App\Support\Reporting\ManagementReport;
use App\Support\Reporting\ReportPeriod;
use App\Support\Sales\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ErpPageController extends Controller
{
    public function pos(Request $request): Response
    {
        Gate::authorize('sales.create');
        $shift = Shift::where('cashier_id', $request->user()->id)->where('status', 'open')->with('location')->first();

        return Inertia::render('sales/pos', ['shift' => $shift, 'expectedCash' => $shift ? (string) ShiftService::expectedCash($shift) : null, 'locations' => Location::where('type', 'sales_floor')->orderBy('name')->get(), 'categories' => Product::where('archived', false)->distinct()->orderBy('category')->pluck('category')]);
    }

    public function sales(Request $request): Response
    {
        abort_unless($request->user()->can('sales.create') || $request->user()->can('sales.view-all'), 403);
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'cashier_id' => ['nullable', 'uuid', 'exists:users,id'], 'due' => ['nullable', 'boolean']]);
        $period = ReportPeriod::fromRequest($request);
        $query = Sale::with('cashier:id,name', 'customer:id,name')->when(! $request->user()->can('sales.view-all'), fn ($q) => $q->where('cashier_id', $request->user()->id))
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'ilike', '%'.$request->input('q').'%'))
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('cashier_id', $request->input('cashier_id')))
            ->when($request->boolean('due'), fn ($q) => $q->whereRaw('grand_total - returned_amount - paid_amount + refunded_amount > 0'));
        // Receipt searches span all dates, so older invoices remain directly findable.
        if (! $request->filled('q')) {
            $period->apply($query);
        }

        return Inertia::render('sales/index', ['sales' => $query->orderByDesc('posted_at')->paginate(20)->withQueryString(), 'filters' => ['q' => $request->input('q', ''), 'from' => $period->from, 'to' => $period->to, 'due' => $request->boolean('due')]]);
    }

    public function sale(Request $request, Sale $sale): Response
    {
        abort_unless($request->user()->can('sales.view-all') || ($request->user()->can('sales.create') && $sale->cashier_id === $request->user()->id), 403);

        return Inertia::render('sales/show', ['sale' => $sale->load('lines', 'payments', 'returns.lines', 'customer', 'cashier:id,name', 'location', 'shift'), 'locations' => Location::orderBy('name')->get(), 'shift' => Shift::where('cashier_id', $request->user()->id)->where('status', 'open')->first()]);
    }

    public function returns(Request $request): Response
    {
        Gate::authorize('sales.view-all');
        $period = ReportPeriod::fromRequest($request);

        return Inertia::render('sales/returns', ['returns' => $period->apply(SalesReturn::with('sale:id,number', 'actor:id,name'))->orderByDesc('posted_at')->paginate(20)->withQueryString(), 'filters' => ['from' => $period->from, 'to' => $period->to]]);
    }

    public function purchasing(Request $request): Response
    {
        abort_unless($request->user()->can('purchasing.manage') || $request->user()->can('finance.manage'), 403);
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id']]);
        $period = ReportPeriod::fromRequest($request);
        $receipts = GoodsReceipt::with('supplier:id,name', 'location:id,name')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'ilike', '%'.$request->input('q').'%')->orWhere('supplier_reference', 'ilike', '%'.$request->input('q').'%')))->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->input('supplier_id')));
        if (! $request->filled('q')) {
            $period->apply($receipts);
        }

        return Inertia::render('purchasing/index', ['receipts' => $receipts->orderByDesc('posted_at')->paginate(20)->withQueryString(), 'orders' => PurchaseOrder::with('supplier:id,name', 'location:id,name')->whereIn('status', ['ordered', 'partial'])->orderByDesc('created_at')->paginate(10, ['*'], 'orders_page')->withQueryString(), 'filters' => ['q' => $request->input('q', ''), 'from' => $period->from, 'to' => $period->to]]);
    }

    public function purchaseCreate(Request $request): Response
    {
        Gate::authorize('inventory.receive');
        $order = $request->filled('order') ? PurchaseOrder::with('lines.product')->findOrFail($request->input('order')) : null;

        return Inertia::render('purchasing/create', ['suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']), 'locations' => Location::orderBy('name')->get(), 'order' => $order]);
    }

    public function receipt(GoodsReceipt $receipt): Response
    {
        abort_unless(auth()->user()->can('purchasing.manage') || auth()->user()->can('finance.manage'), 403);

        return Inertia::render('purchasing/show', ['receipt' => $receipt->load('supplier', 'location', 'lines.product', 'lines.batch', 'payments', 'returns.lines', 'order')]);
    }

    public function inventory(Request $request): Response
    {
        Gate::authorize('inventory.view');
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:low,out']]);
        $query = ManagementReport::stockQuery()->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('products.name', 'ilike', '%'.$request->input('q').'%')->orWhere('products.sku', 'ilike', '%'.$request->input('q').'%')))
            ->when($request->input('status') === 'low', fn ($q) => $q->whereRaw('coalesce(stock.available,0) <= products.reorder_point'))
            ->when($request->input('status') === 'out', fn ($q) => $q->whereRaw('coalesce(stock.available,0) = 0'));

        return Inertia::render('inventory/index', ['products' => $query->orderBy('products.name')->paginate(25)->withQueryString(), 'alerts' => ManagementReport::alerts(), 'filters' => ['q' => $request->input('q', ''), 'status' => $request->input('status', '')]]);
    }

    public function stockOperations(): Response
    {
        abort_unless(auth()->user()->can('inventory.adjust') || auth()->user()->can('inventory.transfer'), 403);

        return Inertia::render('inventory/operations', ['operations' => StockOperation::with('product:id,name,sku', 'location:id,name', 'destination:id,name', 'actor:id,name')->orderByDesc('posted_at')->paginate(20), 'locations' => Location::orderBy('name')->get()]);
    }

    public function expenses(Request $request): Response
    {
        Gate::authorize('finance.manage');
        $period = ReportPeriod::fromRequest($request);

        return Inertia::render('finance/expenses', ['expenses' => Expense::with('category', 'actor:id,name', 'location:id,name')->whereBetween('expense_date', [$period->from, $period->to])->orderByDesc('expense_date')->paginate(20)->withQueryString(), 'categories' => ExpenseCategory::orderBy('name')->get(), 'locations' => Location::orderBy('name')->get(), 'filters' => ['from' => $period->from, 'to' => $period->to]]);
    }

    public function categories(): Response
    {
        Gate::authorize('products.manage');

        return Inertia::render('catalog/categories', ['categories' => Product::where('archived', false)->selectRaw('category as name, count(*) as products, count(distinct brand) as brands')->groupBy('category')->orderBy('category')->get()]);
    }

    public function renameCategory(Request $request)
    {
        Gate::authorize('products.manage');
        $data = $request->validate(['old_name' => ['required', 'string', 'exists:products,category'], 'name' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $data) {
            Product::where('category', $data['old_name'])->update(['category' => $data['name']]);
            AuditLog::record($request->user(), 'CATEGORY_RENAMED', null, $data);
        });

        return back()->with('success', 'Category updated for all matching products.');
    }

    public function audit(): Response
    {
        Gate::authorize('audit.view');

        return Inertia::render('admin/audit', ['entries' => AuditLogEntry::with('actor:id,name')->orderByDesc('created_at')->paginate(30)]);
    }
}
