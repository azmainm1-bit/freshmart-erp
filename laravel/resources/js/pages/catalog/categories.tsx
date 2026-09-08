import { Field } from '@/components/erp/field';
import { Page, Panel, Submit } from '@/components/erp/page';
import { ErpSelect } from '@/components/erp/select';
import { Table } from '@/components/erp/table';
import { Link, useForm } from '@inertiajs/react';
export default function Categories({ categories }: { categories: { name: string; products: number; brands: number }[] }) {
    const form = useForm({ old_name: '', name: '' });
    return <Page title="Product categories" description="Categories organize the product catalog. Assign a category when creating a product, or rename it across the catalog here."><Panel><Table rows={categories.map(c => ({ ...c,id:c.name }))} columns={[{ label: 'Category', render: c => <Link href={`/catalog/products?category=${encodeURIComponent(c.name)}`} className="text-primary font-medium">{c.name}</Link> }, { label: 'Products', render: c => c.products }, { label: 'Brands', render: c => c.brands }]} /></Panel><Panel title="Rename a category"><form className="flex flex-wrap items-end gap-4 p-5" onSubmit={e => { e.preventDefault(); form.post('/catalog/categories/rename',{ onSuccess: () => form.reset() }); }}><Field label="Current category" error={form.errors.old_name}><ErpSelect value={form.data.old_name} onChange={v => form.setData('old_name', v)} placeholder="Choose category" options={categories.map(c => ({ value: c.name, label: c.name }))} /></Field><Field label="New name" error={form.errors.name}><input className="erp-input" required value={form.data.name} onChange={e => form.setData('name',e.target.value)} /></Field><Submit busy={form.processing}>Rename category</Submit></form></Panel></Page>;
}
