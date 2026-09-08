import { CustomerForm } from '@/components/erp/customer-form';
import { Filters, Page, Panel, Status, usePermissions } from '@/components/erp/page';
import { Pagination, Table } from '@/components/erp/table';
import { money } from '@/lib/format';
import { Customer, PageData } from '@/types/erp';
import { Link } from '@inertiajs/react';
export default function Customers({ customers, filters }: { customers: PageData<Customer>; filters: { q: string } }) {
    const can = usePermissions();
    return <Page title="Customers" description="Customer contacts, purchase history and controlled credit accounts."><details className="bg-card rounded-xl border"><summary className="cursor-pointer px-5 py-4 font-medium">+ New customer</summary><CustomerForm /></details><Filters path="/customers" initial={filters} dates={false} /><Panel><Table rows={customers.data} columns={[{ label: 'Customer', render: c => <Link className="text-primary font-medium" href={`/customers/${c.id}`}>{c.name}</Link> }, { label: 'Phone', render: c => c.phone ?? '—' }, { label: 'Email', render: c => c.email ?? '—' }, ...(can('finance.manage') ? [{ label: 'Outstanding', render: (c: Customer) => money(c.balance) }, { label: 'Credit limit', render: (c: Customer) => money(c.credit_limit) }] : []), { label: 'Status', render: c => <Status tone={c.active ? 'good' : 'neutral'}>{c.active ? 'Active' : 'Inactive'}</Status> }]} /><Pagination page={customers} /></Panel></Page>;
}
