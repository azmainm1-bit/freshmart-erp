<?php

namespace App\Support\Reporting;

use Illuminate\Support\Facades\DB;

final class Reconciliation
{
    /** Read-only integrity checks; an empty mismatch set is required for release. */
    public static function check(): array
    {
        $checks = [
            'stock_matches_movements' => <<<'SQL'
                with ledger as (select product_id,location_id,batch_id,sum(quantity_delta) as quantity from stock_movements group by product_id,location_id,batch_id)
                select count(*) as failures from stock_balances b full join ledger l on l.product_id=b.product_id and l.location_id=b.location_id and l.batch_id is not distinct from b.batch_id where coalesce(b.quantity_on_hand,0) <> coalesce(l.quantity,0)
                SQL,
            'sales_match_lines' => 'select count(*) as failures from sales s where s.grand_total <> coalesce((select sum(line_total) from sale_lines where sale_id=s.id),0) or s.subtotal <> coalesce((select sum(subtotal) from sale_lines where sale_id=s.id),0) or s.tax_total <> coalesce((select sum(tax_amount) from sale_lines where sale_id=s.id),0) or s.cost_total <> coalesce((select sum(cost_total) from sale_lines where sale_id=s.id),0)',
            'sale_payments_reconcile' => "select count(*) as failures from sales s where s.paid_amount <> coalesce((select sum(amount) from sales_payments where sale_id=s.id and kind='payment'),0) or s.refunded_amount <> coalesce((select sum(amount) from sales_payments where sale_id=s.id and kind='refund'),0) or s.returned_amount <> coalesce((select sum(total_amount) from sales_returns where sale_id=s.id),0)",
            'sale_allocations_reconcile' => 'select count(*) as failures from sale_lines l where l.quantity <> coalesce((select sum(quantity) from sale_allocations where sale_line_id=l.id),0) or l.returned_quantity <> coalesce((select sum(returned_quantity) from sale_allocations where sale_line_id=l.id),0) or l.returned_quantity <> coalesce((select sum(quantity) from sales_return_lines where sale_line_id=l.id),0)',
            'sale_stock_postings_reconcile' => "select count(*) as failures from sale_lines l where l.quantity <> coalesce((select -sum(quantity_delta) from stock_movements where source_document_type='Sale' and source_document_id=l.sale_id and product_id=l.product_id),0)",
            'receipts_and_payables_reconcile' => 'select count(*) as failures from goods_receipts r where r.total_amount <> coalesce((select sum(line_total) from goods_receipt_lines where goods_receipt_id=r.id),0) or r.paid_amount <> coalesce((select sum(amount) from supplier_payments where goods_receipt_id=r.id),0) or r.returned_amount <> coalesce((select sum(total_amount) from purchase_returns where goods_receipt_id=r.id),0)',
            'return_headers_reconcile' => 'select count(*) as failures from sales_returns r where r.total_amount <> coalesce((select sum(total_amount) from sales_return_lines where sales_return_id=r.id),0) or r.tax_amount <> coalesce((select sum(tax_amount) from sales_return_lines where sales_return_id=r.id),0) or r.cost_amount <> coalesce((select sum(cost_amount) from sales_return_lines where sales_return_id=r.id),0)',
            'transfers_balance' => "select count(*) as failures from stock_operations o where o.type='transfer' and (coalesce((select sum(quantity_delta) from stock_movements where source_document_type='StockOperation' and source_document_id=o.id),0) <> 0 or (select count(*) from stock_movements where source_document_type='StockOperation' and source_document_id=o.id) <> 2)",
            'closed_shifts_reconcile' => "select count(*) as failures from shifts s where status='closed' and (expected_cash <> opening_cash + coalesce((select sum(case when kind='payment' then amount else -amount end) from sales_payments where shift_id=s.id and method='cash'),0) + coalesce((select sum(case when type='in' then amount else -amount end) from cash_movements where shift_id=s.id),0) or variance <> counted_cash-expected_cash)",
        ];

        return array_map(fn ($sql) => (int) DB::selectOne($sql)->failures, $checks);
    }
}
