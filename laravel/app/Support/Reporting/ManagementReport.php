<?php

namespace App\Support\Reporting;

use App\Support\BusinessDate;
use App\Support\Decimal\Money;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ManagementReport
{
    public static function summary(ReportPeriod $period): array
    {
        $sales = $period->apply(DB::table('sales'))->selectRaw('count(*) as transactions, coalesce(sum(grand_total),0)::text as gross, coalesce(sum(subtotal),0)::text as revenue, coalesce(sum(tax_total),0)::text as tax, coalesce(sum(discount_total),0)::text as discount, coalesce(sum(cost_total),0)::text as cost')->first();
        $returns = $period->apply(DB::table('sales_returns'))->selectRaw('coalesce(sum(total_amount),0)::text as total, coalesce(sum(tax_amount),0)::text as tax, coalesce(sum(cost_amount),0)::text as cost')->first();
        $purchases = Money::of((string) $period->apply(DB::table('goods_receipts'))->sum('total_amount'));
        $purchaseReturns = Money::of((string) $period->apply(DB::table('purchase_returns'))->sum('total_amount'));
        $expenses = Money::of((string) DB::table('expenses')->whereBetween('expense_date', [$period->from, $period->to])->sum('amount'));
        $writeOffs = Money::of((string) $period->apply(DB::table('stock_movements'), 'created_at')->where('movement_type', 'write_off')->selectRaw('coalesce(sum(-quantity_delta * unit_cost),0)::text as total')->first()->total);
        $netSales = Money::of((string) $sales->revenue)->minus(Money::of((string) $returns->total)->minus((string) $returns->tax));
        $netCost = Money::of((string) $sales->cost)->minus((string) $returns->cost);
        $profit = $netSales->minus($netCost);
        $cashIn = Money::of((string) $period->apply(DB::table('sales_payments'), 'paid_at')->where('method', 'cash')->selectRaw("coalesce(sum(case when kind = 'payment' then amount else -amount end),0)::text as total")->first()->total);
        $cashSuppliers = Money::of((string) $period->apply(DB::table('supplier_payments'), 'paid_at')->where('method', 'cash')->sum('amount'));
        $cashExpenses = Money::of((string) DB::table('expenses')->whereBetween('expense_date', [$period->from, $period->to])->where('method', 'cash')->sum('amount'));
        $balances = DB::table('goods_receipts')->selectRaw('coalesce(sum(greatest(total_amount - returned_amount - paid_amount,0)),0)::text as payable, coalesce(sum(greatest(paid_amount + returned_amount - total_amount,0)),0)::text as credit')->first();

        return [
            'gross_sales' => (string) Money::of((string) $sales->gross), 'net_sales' => (string) $netSales,
            'sales_returns' => (string) Money::of((string) $returns->total),
            'tax' => (string) Money::of((string) $sales->tax)->minus((string) $returns->tax),
            'discounts' => (string) Money::of((string) $sales->discount), 'cost_of_sales' => (string) $netCost,
            'gross_profit' => (string) $profit, 'margin' => $netSales->isPositive() ? (string) $profit->multipliedBy(100)->dividedBy($netSales->toBigDecimal()) : null,
            'purchases' => (string) $purchases->minus($purchaseReturns), 'expenses' => (string) $expenses,
            'write_offs' => (string) $writeOffs, 'operating_result' => (string) $profit->minus($expenses)->minus($writeOffs),
            'cash_flow' => (string) $cashIn->minus($cashSuppliers)->minus($cashExpenses),
            'transactions' => (int) $sales->transactions,
            'average_order' => $sales->transactions ? (string) Money::of((string) $sales->gross)->dividedBy((int) $sales->transactions) : '0.00',
            'receivables' => (string) Money::of((string) DB::table('sales')->selectRaw('coalesce(sum(grand_total - returned_amount - paid_amount + refunded_amount),0)::text as total')->first()->total),
            'payables' => (string) Money::of((string) $balances->payable), 'supplier_credits' => (string) Money::of((string) $balances->credit),
            'inventory_value' => (string) Money::of((string) DB::table('stock_balances')->selectRaw('coalesce(sum(quantity_on_hand * average_cost),0)::text as total')->first()->total),
        ];
    }

    public static function stockQuery()
    {
        $stock = DB::table('stock_balances')->join('locations', 'locations.id', '=', 'stock_balances.location_id')->leftJoin('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->where('locations.type', '<>', 'quarantine')->where(fn ($q) => $q->whereNull('batches.expiry_date')->orWhere('batches.expiry_date', '>=', BusinessDate::today()))
            ->selectRaw('stock_balances.product_id, sum(quantity_on_hand) as available')->groupBy('stock_balances.product_id');

        return DB::table('products')->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')->where('products.archived', false)
            ->select('products.id', 'products.sku', 'products.name', 'products.category', 'products.stock_unit', 'products.reorder_point', 'products.selling_price')->selectRaw('coalesce(stock.available,0)::text as available');
    }

    public static function alerts(): array
    {
        $low = self::stockQuery()->whereRaw('coalesce(stock.available,0) <= products.reorder_point');

        return [
            'low_count' => (clone $low)->count(),
            'out_count' => self::stockQuery()->whereRaw('coalesce(stock.available,0) = 0')->count(),
            'low_stock' => $low->orderByRaw('coalesce(stock.available,0)')->orderBy('products.name')->limit(8)->get(),
            'expiring' => DB::table('stock_balances')->join('products', 'products.id', '=', 'stock_balances.product_id')->join('batches', 'batches.id', '=', 'stock_balances.batch_id')->join('locations', 'locations.id', '=', 'stock_balances.location_id')
                ->where('quantity_on_hand', '>', 0)->where('locations.type', '<>', 'quarantine')->whereBetween('expiry_date', [BusinessDate::today(), now(config('erp.timezone'))->addDays(30)->format('Y-m-d')])
                ->orderBy('expiry_date')->limit(8)->get(['products.name', 'products.id', 'batch_no', 'expiry_date', 'quantity_on_hand', 'locations.name as location']),
        ];
    }

    public static function trend(ReportPeriod $period): array
    {
        $sales = $period->apply(DB::table('sales'))->selectRaw("to_char(posted_at AT TIME ZONE 'UTC' AT TIME ZONE ?, 'YYYY-MM-DD') as day, sum(subtotal)::text as net_sales, sum(subtotal - cost_total)::text as profit", [config('erp.timezone')])->groupBy('day')->get()->keyBy('day');
        $returns = $period->apply(DB::table('sales_returns'))->selectRaw("to_char(posted_at AT TIME ZONE 'UTC' AT TIME ZONE ?, 'YYYY-MM-DD') as day, sum(total_amount-tax_amount)::text as revenue, sum(total_amount-tax_amount-cost_amount)::text as profit", [config('erp.timezone')])->groupBy('day')->get()->keyBy('day');
        $rows = [];
        foreach (CarbonPeriod::create($period->from, $period->to) as $date) {
            $day = $date->format('Y-m-d');
            $rows[] = ['day' => $day, 'net_sales' => (string) Money::of((string) ($sales->get($day)->net_sales ?? '0'))->minus((string) ($returns->get($day)->revenue ?? '0')), 'profit' => (string) Money::of((string) ($sales->get($day)->profit ?? '0'))->minus((string) ($returns->get($day)->profit ?? '0'))];
        }

        return $rows;
    }
}
