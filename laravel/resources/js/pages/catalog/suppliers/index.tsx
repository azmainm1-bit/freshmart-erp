import { Field } from '@/components/erp/field';
import { Page, Panel } from '@/components/erp/page';
import { Pagination } from '@/components/erp/table';
import { Button } from '@/components/ui/button';
import { PageData } from '@/types/erp';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface SupplierRow {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    active: boolean;
}

export default function SuppliersIndex({ suppliers, filters }: { suppliers: PageData<SupplierRow>; filters: { q: string; inactive: boolean } }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', phone: '', address: '' });
    const [search, setSearch] = useState(filters.q);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/catalog/suppliers', { onSuccess: () => reset() });
    };
    function runSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/catalog/suppliers', { q: search, inactive: filters.inactive ? '1' : '0' }, { preserveState: true });
    }
    function toggleInactiveView() {
        router.get('/catalog/suppliers', { q: filters.q, inactive: filters.inactive ? '0' : '1' }, { preserveState: true });
    }

    return (
        <Page title="Suppliers" description="Vendors you purchase and receive stock from. Deactivate a supplier instead of deleting it to preserve purchase history.">
            <Panel title="New supplier">
                <form onSubmit={submit} className="grid gap-4 p-5 sm:grid-cols-3">
                    <Field label="Name" error={errors.name}><input className="erp-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
                    <Field label="Phone" error={errors.phone}><input className="erp-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></Field>
                    <Field label="Address" error={errors.address}><input className="erp-input" value={data.address} onChange={(e) => setData('address', e.target.value)} /></Field>
                    <div className="sm:col-span-3"><Button type="submit" disabled={processing}>Create supplier</Button></div>
                </form>
            </Panel>

            <Panel
                title={filters.inactive ? 'Inactive suppliers' : 'Suppliers'}
                action={
                    <div className="flex items-center gap-2">
                        <form onSubmit={runSearch} className="flex gap-2">
                            <input className="erp-input w-56" placeholder="Search by name…" aria-label="Search suppliers" value={search} onChange={(e) => setSearch(e.target.value)} />
                            <Button type="submit" variant="secondary">Search</Button>
                        </form>
                        <Button type="button" variant="outline" onClick={toggleInactiveView}>{filters.inactive ? 'Show active' : 'Show inactive'}</Button>
                    </div>
                }
            >
                <div className="w-full overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b text-xs">
                            <tr>
                                <th scope="col" className="px-5 py-3 font-medium">Name</th>
                                <th scope="col" className="px-5 py-3 font-medium">Phone</th>
                                <th scope="col" className="px-5 py-3 font-medium">Address</th>
                                <th scope="col" className="px-5 py-3 font-medium"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {suppliers.data.length === 0 && <tr><td colSpan={4} className="text-muted-foreground px-5 py-10 text-center">No suppliers found.</td></tr>}
                            {suppliers.data.map((s) => <SupplierRowView key={s.id} supplier={s} />)}
                        </tbody>
                    </table>
                </div>
                <Pagination page={suppliers} />
            </Panel>
        </Page>
    );
}

function SupplierRowView({ supplier }: { supplier: SupplierRow }) {
    const { data, setData, put, processing, isDirty } = useForm({ name: supplier.name, phone: supplier.phone ?? '', address: supplier.address ?? '', active: supplier.active });
    const save = () => put(`/catalog/suppliers/${supplier.id}`);
    const toggleActive = () => { setData('active', !data.active); router.put(`/catalog/suppliers/${supplier.id}`, { ...data, active: !data.active }); };
    return (
        <tr className="hover:bg-muted/30 align-top transition-colors">
            <td className="px-5 py-4"><input className="erp-input" value={data.name} onChange={(e) => setData('name', e.target.value)} /></td>
            <td className="px-5 py-4"><input className="erp-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></td>
            <td className="px-5 py-4"><input className="erp-input" value={data.address} onChange={(e) => setData('address', e.target.value)} /></td>
            <td className="flex flex-wrap gap-2 px-5 py-4">
                <Button type="button" size="sm" onClick={save} disabled={processing || !isDirty}>Save</Button>
                <Button type="button" size="sm" variant={supplier.active ? 'destructive' : 'outline'} onClick={toggleActive}>{supplier.active ? 'Deactivate' : 'Reactivate'}</Button>
            </td>
        </tr>
    );
}
