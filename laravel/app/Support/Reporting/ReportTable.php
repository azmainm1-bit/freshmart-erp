<?php

namespace App\Support\Reporting;

use Illuminate\Support\Facades\DB;

class ReportTable
{
    public const TYPES = ['sales', 'purchases', 'payments', 'returns', 'expenses', 'stock', 'movements', 'products', 'categories', 'cashiers', 'customers', 'suppliers'];

    public static function query(string $type, ReportPeriod $period, array $filters = [])
    {
        $q = match ($type) {
            'sales' => $period->apply(DB::table('sales')->join('users', 'users.id', '=', 'sales.cashier_id'), 'sales.posted_at')->select('sales.id', 'sales.posted_at as date', 'sales.number as reference', 'users.name as party', 'sales.grand_total as amount')->selectRaw("(grand_total - returned_amount - paid_amount + refunded_amount)::text as balance, 'Sale' as description"),
            'purchases' => $period->apply(DB::table('goods_receipts')->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id'), 'goods_receipts.posted_at')->select('goods_receipts.id', 'goods_receipts.posted_at as date', 'goods_receipts.number as reference', 'suppliers.name as party', 'goods_receipts.total_amount as amount')->selectRaw("(total_amount - returned_amount - paid_amount)::text as balance, 'Goods receipt' as description"),
            'returns' => $period->apply(DB::table('sales_returns')->join('sales', 'sales.id', '=', 'sales_returns.sale_id'), 'sales_returns.posted_at')->select('sales_returns.id', 'sales_returns.posted_at as date', 'sales_returns.number as reference', 'sales.number as party', 'sales_returns.reason as description', 'sales_returns.total_amount as amount', 'sales_returns.refund_amount as balance'),
            'expenses' => DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->whereBetween('expense_date', [$period->from, $period->to])->select('expenses.id', 'expense_date as date', 'number as reference', 'expense_categories.name as party', 'description', 'amount')->selectRaw('0::numeric as balance'),
            'stock' => DB::table('stock_balances')->join('products', 'products.id', '=', 'stock_balances.product_id')->join('locations', 'locations.id', '=', 'stock_balances.location_id')->select('stock_balances.id', 'products.sku as reference', 'products.name as description', 'locations.name as party', 'quantity_on_hand as quantity')->selectRaw('round(quantity_on_hand * average_cost,2)::text as amount, average_cost::text as balance'),
            'movements' => $period->apply(DB::table('stock_movements')->join('products', 'products.id', '=', 'stock_movements.product_id')->join('locations', 'locations.id', '=', 'stock_movements.location_id'), 'stock_movements.created_at')->select('stock_movements.id', 'stock_movements.created_at as date', 'products.sku as reference', 'products.name as description', 'locations.name as party', 'quantity_delta as quantity', 'movement_type as status')->selectRaw('round(quantity_delta * unit_cost,2)::text as amount, unit_cost::text as balance'),
            'customers' => DB::table('customers')->leftJoin('sales', 'sales.customer_id', '=', 'customers.id')->select('customers.id', 'customers.name as description', 'customers.phone as reference')->selectRaw("'Customer' as party, coalesce(sum(grand_total-returned_amount),0)::text as amount, coalesce(sum(grand_total-returned_amount-paid_amount+refunded_amount),0)::text as balance")->groupBy('customers.id'),
            'suppliers' => DB::table('suppliers')->leftJoin('goods_receipts', 'goods_receipts.supplier_id', '=', 'suppliers.id')->select('suppliers.id', 'suppliers.name as description', 'suppliers.phone as reference')->selectRaw("'Supplier' as party, coalesce(sum(total_amount-returned_amount),0)::text as amount, coalesce(sum(total_amount-returned_amount-paid_amount),0)::text as balance")->groupBy('suppliers.id'),
            'products', 'categories', 'cashiers' => self::performance($type, $period),
            'payments' => self::payments($period),
            default => throw new \InvalidArgumentException('Unknown report.'),
        };
        // Filtering only on columns that belong to this report prevents misleading scope.
        if (! empty($filters['location_id']) && in_array($type, ['sales', 'purchases', 'stock', 'movements', 'expenses', 'returns'])) {
            $table = match ($type) {
                'purchases' => 'goods_receipts', 'stock' => 'stock_balances', 'movements' => 'stock_movements', 'returns' => 'sales_returns', default => $type
            };
            $q->where($table.'.location_id', $filters['location_id']);
        }
        if (! empty($filters['cashier_id']) && $type === 'sales') {
            $q->where('sales.cashier_id', $filters['cashier_id']);
        }
        if (! empty($filters['method']) && $type === 'payments') {
            $q->where('ledger.method', $filters['method']);
        }

        return $q;
    }

    private static function payments(ReportPeriod $period)
    {
        $sales = $period->apply(DB::table('sales_payments')->join('sales', 'sales.id', '=', 'sales_payments.sale_id'), 'paid_at')->select('sales_payments.id', 'paid_at as date', 'sales.number as reference', 'sales_payments.method')->selectRaw("case when kind='refund' then -amount else amount end as amount, case when kind='refund' then 'Customer refund' else 'Customer payment' end as description");
        $suppliers = $period->apply(DB::table('supplier_payments')->join('goods_receipts', 'goods_receipts.id', '=', 'supplier_payments.goods_receipt_id'), 'paid_at')->select('supplier_payments.id', 'paid_at as date', 'goods_receipts.number as reference', 'method')->selectRaw("-amount as amount, 'Supplier payment' as description");
        $expenses = DB::table('expenses')->whereBetween('expense_date', [$period->from, $period->to])->select('id', 'expense_date as date', 'number as reference', 'method')->selectRaw("-amount as amount, 'Expense payment' as description");

        return DB::query()->fromSub($sales->unionAll($suppliers)->unionAll($expenses), 'ledger')->select('ledger.*')->selectRaw('method as party, 0::numeric as balance');
    }

    private static function performance(string $type, ReportPeriod $period)
    {
        // Cohort report: sales posted in this period, less all returns to those lines.
        $returns = DB::table('sales_return_lines')->selectRaw('sale_line_id, sum(total_amount-tax_amount) as net, sum(cost_amount) as cost, sum(quantity) as quantity')->groupBy('sale_line_id');
        $query = $period->apply(DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->join('products', 'products.id', '=', 'sale_lines.product_id')->join('users', 'users.id', '=', 'sales.cashier_id')->leftJoinSub($returns, 'returned', 'returned.sale_line_id', '=', 'sale_lines.id'), 'sales.posted_at');
        [$id, $name, $reference] = match ($type) {
            'categories' => ['products.category', 'products.category', 'products.category'], 'cashiers' => ['users.id', 'users.name', 'users.username'], default => ['products.id', 'products.name', 'products.sku']
        };

        return $query->selectRaw("{$id} as id, {$name} as description, {$reference} as reference, 'Sales cohort' as party, sum(sale_lines.quantity - coalesce(returned.quantity,0))::text as quantity, sum(sale_lines.subtotal-coalesce(returned.net,0))::text as amount, sum(sale_lines.subtotal-coalesce(returned.net,0)-sale_lines.cost_total+coalesce(returned.cost,0))::text as balance")
            ->groupBy($id, $name, $reference);
    }
}
