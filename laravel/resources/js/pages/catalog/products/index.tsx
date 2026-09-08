import { Field } from '@/components/erp/field';
import { Page, Panel } from '@/components/erp/page';
import { Pagination } from '@/components/erp/table';
import { ErpSelect } from '@/components/erp/select';
import { Button } from '@/components/ui/button';
import { PageData } from '@/types/erp';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Supplier {
    id: string;
    name: string;
}

interface ProductRow {
    id: string;
    sku: string;
    name: string;
    name_bn: string | null;
    category: string;
    brand: string | null;
    stock_unit: string;
    selling_price: string;
    tax_rate_percent: string;
    tax_inclusive: boolean;
    reorder_point: string;
    archived: boolean;
    default_supplier_id: string | null;
    barcodes: { id: string; code: string }[];
}

export default function ProductsIndex({ products, filters, suppliers }: { products: PageData<ProductRow>; filters: { q: string; archived: boolean }; suppliers: Supplier[] }) {
    const [search, setSearch] = useState(filters.q);

    function runSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/catalog/products', { q: search, archived: filters.archived ? '1' : '0' }, { preserveState: true });
    }

    function toggleArchivedView() {
        router.get('/catalog/products', { q: filters.q, archived: filters.archived ? '0' : '1' }, { preserveState: true });
    }

    return (
        <Page title="Products" description="Catalog master data — pricing, tax and reorder settings for every product you sell.">
            <CreateProductForm suppliers={suppliers} />

            <Panel
                title={filters.archived ? 'Archived products' : 'Products'}
                action={
                    <div className="flex items-center gap-2">
                        <form onSubmit={runSearch} className="flex gap-2">
                            <input className="erp-input w-64" placeholder="Search by name, SKU, or barcode…" aria-label="Search products" value={search} onChange={(e) => setSearch(e.target.value)} />
                            <Button type="submit" variant="secondary">Search</Button>
                        </form>
                        <Button type="button" variant="outline" onClick={toggleArchivedView}>{filters.archived ? 'Show active' : 'Show archived'}</Button>
                    </div>
                }
            >
                <div className="w-full overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b text-xs">
                            <tr>
                                <th scope="col" className="px-5 py-3 font-medium">SKU</th>
                                <th scope="col" className="px-5 py-3 font-medium">Name</th>
                                <th scope="col" className="px-5 py-3 font-medium">Category</th>
                                <th scope="col" className="px-5 py-3 font-medium">Price (BDT)</th>
                                <th scope="col" className="px-5 py-3 font-medium">Reorder at</th>
                                <th scope="col" className="px-5 py-3 font-medium"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.length === 0 && (
                                <tr><td colSpan={6} className="text-muted-foreground px-5 py-10 text-center">No products found. Try a different search or create your first product above.</td></tr>
                            )}
                            {products.data.map((p) => <ProductRowView key={p.id} product={p} />)}
                        </tbody>
                    </table>
                </div>
                <Pagination page={products} />
            </Panel>
        </Page>
    );
}

type ProductCreateFormData = {
    sku: string;
    name: string;
    name_bn: string;
    category: string;
    brand: string;
    stock_unit: string;
    purchase_unit: string;
    pack_conversion_factor: string;
    selling_price: string;
    tax_rate_percent: string;
    tax_inclusive: boolean;
    is_weighted: boolean;
    is_batch_tracked: boolean;
    reorder_point: string;
    default_supplier_id: string;
    barcodes: string;
};
function CreateProductForm({ suppliers }: { suppliers: Supplier[] }) {
    const { data, setData, post, processing, errors, reset, transform } = useForm<ProductCreateFormData>({
        sku: '',
        name: '',
        name_bn: '',
        category: '',
        brand: '',
        stock_unit: 'unit',
        purchase_unit: 'unit',
        pack_conversion_factor: '1',
        selling_price: '',
        tax_rate_percent: '0',
        tax_inclusive: true,
        is_weighted: false,
        is_batch_tracked: false,
        reorder_point: '0',
        default_supplier_id: '',
        barcodes: '',
    });

    transform((formData) => ({
        ...formData,
        default_supplier_id: formData.default_supplier_id || null,
        barcodes: formData.barcodes
            ? String(formData.barcodes)
                  .split(',')
                  .map((s: string) => s.trim())
                  .filter(Boolean)
            : [],
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/catalog/products', { onSuccess: () => reset('sku', 'name', 'name_bn', 'selling_price', 'barcodes') });
    };

    return (
        <Panel title="New product">
            <form onSubmit={submit} className="space-y-5 p-5">
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <Field label="SKU" error={errors.sku}><input className="erp-input" value={data.sku} onChange={(e) => setData('sku', e.target.value)} required /></Field>
                    <Field label="Name (English)" error={errors.name}><input className="erp-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
                    <Field label="Name (Bangla)" error={errors.name_bn}><input className="erp-input" value={data.name_bn} onChange={(e) => setData('name_bn', e.target.value)} lang="bn" /></Field>
                    <Field label="Category" error={errors.category}><input className="erp-input" value={data.category} onChange={(e) => setData('category', e.target.value)} required /></Field>
                    <Field label="Brand" error={errors.brand}><input className="erp-input" value={data.brand} onChange={(e) => setData('brand', e.target.value)} /></Field>
                    <Field label="Default supplier" error={errors.default_supplier_id}><ErpSelect value={data.default_supplier_id} onChange={(v) => setData('default_supplier_id', v)} placeholder="No default supplier" options={suppliers.map((s) => ({ value: s.id, label: s.name }))} /></Field>
                    <Field label="Stock unit" error={errors.stock_unit}><input className="erp-input" value={data.stock_unit} onChange={(e) => setData('stock_unit', e.target.value)} required /></Field>
                    <Field label="Purchase unit" error={errors.purchase_unit}><input className="erp-input" value={data.purchase_unit} onChange={(e) => setData('purchase_unit', e.target.value)} required /></Field>
                    <Field label="Pack conversion factor" error={errors.pack_conversion_factor}><input className="erp-input" value={data.pack_conversion_factor} onChange={(e) => setData('pack_conversion_factor', e.target.value)} required /></Field>
                    <Field label="Selling price (BDT)" error={errors.selling_price}><input className="erp-input" type="number" step="0.01" value={data.selling_price} onChange={(e) => setData('selling_price', e.target.value)} required /></Field>
                    <Field label="Tax rate %" error={errors.tax_rate_percent}><input className="erp-input" type="number" step="0.01" value={data.tax_rate_percent} onChange={(e) => setData('tax_rate_percent', e.target.value)} required /></Field>
                    <Field label="Reorder point" error={errors.reorder_point}><input className="erp-input" type="number" step="0.01" value={data.reorder_point} onChange={(e) => setData('reorder_point', e.target.value)} required /></Field>
                    <Field label="Barcode(s), comma-separated" error={errors.barcodes}><input className="erp-input" value={data.barcodes} onChange={(e) => setData('barcodes', e.target.value)} /></Field>
                </div>
                <div className="flex flex-wrap gap-6">
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.is_weighted} onChange={(e) => setData('is_weighted', e.target.checked)} />Sold by weight (fractional quantity)</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.is_batch_tracked} onChange={(e) => setData('is_batch_tracked', e.target.checked)} />Track batch/expiry</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.tax_inclusive} onChange={(e) => setData('tax_inclusive', e.target.checked)} />Price is tax-inclusive</label>
                </div>
                <Button type="submit" disabled={processing}>Create product</Button>
            </form>
        </Panel>
    );
}

type ProductEditFormData = {
    name: string;
    name_bn: string;
    category: string;
    brand: string;
    selling_price: string;
    tax_rate_percent: string;
    tax_inclusive: boolean;
    reorder_point: string;
    default_supplier_id: string;
    archived: boolean;
};
function ProductRowView({ product }: { product: ProductRow }) {
    const { data, setData, put, processing, isDirty } = useForm<ProductEditFormData>({
        name: product.name,
        name_bn: product.name_bn ?? '',
        category: product.category,
        brand: product.brand ?? '',
        selling_price: product.selling_price,
        tax_rate_percent: product.tax_rate_percent,
        tax_inclusive: product.tax_inclusive,
        reorder_point: product.reorder_point,
        default_supplier_id: product.default_supplier_id ?? '',
        archived: product.archived,
    });

    const save = () => put(`/catalog/products/${product.id}`);
    const toggleArchived = () => {
        setData('archived', !data.archived);
        router.put(`/catalog/products/${product.id}`, { ...data, archived: !data.archived });
    };

    return (
        <tr className="hover:bg-muted/30 align-top transition-colors">
            <td className="px-5 py-4 font-mono text-xs">{product.sku}</td>
            <td className="px-5 py-4"><input className="erp-input" value={data.name} onChange={(e) => setData('name', e.target.value)} /></td>
            <td className="px-5 py-4"><input className="erp-input" value={data.category} onChange={(e) => setData('category', e.target.value)} /></td>
            <td className="px-5 py-4"><input className="erp-input w-24" value={data.selling_price} onChange={(e) => setData('selling_price', e.target.value)} /></td>
            <td className="px-5 py-4"><input className="erp-input w-20" value={data.reorder_point} onChange={(e) => setData('reorder_point', e.target.value)} /></td>
            <td className="flex flex-wrap gap-2 px-5 py-4">
                <Button type="button" size="sm" onClick={save} disabled={processing || !isDirty}>Save</Button>
                <Button type="button" size="sm" variant={product.archived ? 'outline' : 'destructive'} onClick={toggleArchived}>{product.archived ? 'Restore' : 'Archive'}</Button>
                <Link href={`/inventory/stock/${product.id}`} className="text-primary self-center text-sm underline">Stock</Link>
            </td>
        </tr>
    );
}
