import { CustomerForm } from '@/components/erp/customer-form';
import { Metric, Page, Panel } from '@/components/erp/page';
import { Pagination, Table } from '@/components/erp/table';
import { date, money } from '@/lib/format';
import { due } from '@/lib/pricing';
import { Customer, PageData, Sale } from '@/types/erp';
import { Link } from '@inertiajs/react';
export default function CustomerShow({ customer, sales, balance }: { customer: Customer; sales: PageData<Sale>; balance: string | null }) {
    return <Page title={customer.name} description={[customer.phone,customer.email,customer.active ? 'Active customer' : 'Inactive customer'].filter(Boolean).join(' · ')}>{balance !== null && <div className="grid gap-4 sm:grid-cols-2"><Metric label="Current outstanding balance" value={money(balance)} /><Metric label="Credit limit" value={money(customer.credit_limit)} /></div>}<details className="bg-card rounded-xl border"><summary className="cursor-pointer px-5 py-4 font-medium">Edit customer details</summary><CustomerForm customer={customer} /></details><Panel title="Purchase history" description="Open an invoice to view details or collect an outstanding balance."><Table rows={sales.data} columns={[{ label: 'Receipt', render: s => <Link className="text-primary font-medium" href={`/sales/${s.id}`}>{s.number}</Link> }, { label: 'Date', render: s => date(s.posted_at) }, { label: 'Total', render: s => money(s.grand_total) }, { label: 'Returned', render: s => money(s.returned_amount) }, { label: 'Due', render: s => money(due(s.grand_total,s.returned_amount,s.paid_amount,s.refunded_amount)) }]} /><Pagination page={sales} /></Panel></Page>;
}
