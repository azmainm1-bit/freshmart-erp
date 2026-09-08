import { ActionLink, Filters, Page, Panel, Status, usePermissions } from '@/components/erp/page';
import { Pagination, Table } from '@/components/erp/table';
import { date, money } from '@/lib/format';
import { due } from '@/lib/pricing';
import { PageData, Sale } from '@/types/erp';
import { Link } from '@inertiajs/react';

export default function SalesIndex({ sales, filters }: { sales: PageData<Sale>; filters: { q: string; from: string; to: string; due: boolean } }) {
    const can = usePermissions();
    return <Page title="Sales history" description="Find receipts, review payments and process returns. Search a receipt number across all dates." actions={can('sales.create') && <ActionLink href="/pos">New sale</ActionLink>}><Filters path="/sales" initial={filters} /><Panel><Table rows={sales.data} columns={[{ label: 'Receipt', render: s => <Link className="text-primary font-medium" href={`/sales/${s.id}`}>{s.number}</Link> }, { label: 'Customer / cashier', render: s => <div>{s.customer?.name ?? 'Walk-in customer'}<p className="text-muted-foreground text-xs">{s.cashier?.name}</p></div> }, { label: 'Date', render: s => date(s.posted_at, true) }, { label: 'Total', className: 'text-right tabular-nums', render: s => money(s.grand_total) }, { label: 'Payment state', render: s => Number(due(s.grand_total, s.returned_amount, s.paid_amount, s.refunded_amount)) > 0 ? <Status tone="warning">{money(due(s.grand_total, s.returned_amount, s.paid_amount, s.refunded_amount))} due</Status> : <Status tone={Number(s.returned_amount) > 0 ? 'neutral' : 'good'}>{Number(s.returned_amount) > 0 ? 'Returned / settled' : 'Paid'}</Status> }]} /><Pagination page={sales} /></Panel></Page>;
}
