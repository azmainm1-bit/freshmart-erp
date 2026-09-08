import { ActionLink, Filters, Page, Panel, Status } from '@/components/erp/page';
import { Pagination, Table } from '@/components/erp/table';
import { date, money } from '@/lib/format';
import { PageData, SalesReturn } from '@/types/erp';
import { Link } from '@inertiajs/react';
export default function Returns({ returns, filters }: { returns: PageData<SalesReturn>; filters: { from: string; to: string } }) {
    return <Page title="Sales returns" description="Credit notes and refunds linked to the original receipt. Open a sale to create a return." actions={<ActionLink href="/sales">Find original sale</ActionLink>}><Filters path="/returns" initial={filters} search={false} /><Panel><Table rows={returns.data} columns={[{ label: 'Return', render: r => <span className="font-medium">{r.number}</span> }, { label: 'Original sale', render: r => <Link className="text-primary" href={`/sales/${r.sale?.id}`}>{r.sale?.number}</Link> }, { label: 'Date', render: r => date(r.posted_at) }, { label: 'Reason', render: r => r.reason }, { label: 'Stock', render: r => <Status tone={r.disposition === 'quarantine' ? 'warning' : 'good'}>{r.disposition}</Status> }, { label: 'Credit total', render: r => money(r.total_amount) }, { label: 'Money refunded', render: r => money(r.refund_amount) }]} /><Pagination page={returns} /></Panel></Page>;
}
