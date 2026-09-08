import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ApiRequestError, api, newIdempotencyKey } from '@/lib/api';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Receive Stock', href: '/inventory/goods-receipts/create' }];

interface Option {
    id: string;
    name: string;
}

interface ProductOption {
    id: string;
    sku: string;
    name: string;
    stock_unit: string;
}

export default function GoodsReceiptCreate({
    suppliers,
    locations,
    products,
}: {
    suppliers: Option[];
    locations: (Option & { type: string })[];
    products: ProductOption[];
}) {
    const [supplierId, setSupplierId] = useState('');
    const [locationId, setLocationId] = useState('');
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [unitCost, setUnitCost] = useState('');
    const [batchNo, setBatchNo] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [result, setResult] = useState<string | null>(null);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        setErrors({});
        setResult(null);
        try {
            const res = await api.post<{ goods_receipt: { id: string } }>(
                '/api/goods-receipts',
                {
                    supplier_id: supplierId,
                    location_id: locationId,
                    lines: [
                        {
                            product_id: productId,
                            quantity_received: quantity,
                            unit_cost: unitCost,
                            batch_no: batchNo || null,
                        },
                    ],
                },
                newIdempotencyKey(),
            );
            setResult(`Goods receipt posted: ${res.goods_receipt.id}`);
            setQuantity('');
            setUnitCost('');
            setBatchNo('');
        } catch (err) {
            if (err instanceof ApiRequestError) {
                if (err.status === 422 && err.body.details && typeof err.body.details === 'object') {
                    setErrors(
                        Object.fromEntries(
                            Object.entries(err.body.details as Record<string, string[]>).map(([k, v]) => [k, v.join(', ')]),
                        ),
                    );
                }
                setError(`${err.body.error}: ${err.body.message}`);
            } else {
                setError('Something went wrong. Please try again.');
            }
        } finally {
            setBusy(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receive Stock" />
            <div className="p-4">
                <form onSubmit={submit} className="max-w-2xl rounded-xl border p-4" aria-labelledby="receive-heading">
                    <h2 id="receive-heading" className="mb-1 text-lg font-semibold">
                        Receive stock
                    </h2>
                    <p className="text-muted-foreground mb-4 text-sm">
                        Increases stock at the chosen location and updates the moving average cost.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label>Supplier</Label>
                            <Select value={supplierId} onValueChange={setSupplierId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {suppliers.map((s) => (
                                        <SelectItem key={s.id} value={s.id}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.supplier_id} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Receiving location</Label>
                            <Select value={locationId} onValueChange={setLocationId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {locations.map((l) => (
                                        <SelectItem key={l.id} value={l.id}>
                                            {l.name} ({l.type.replace('_', ' ')})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.location_id} />
                        </div>
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label>Product</Label>
                            <Select value={productId} onValueChange={setProductId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((p) => (
                                        <SelectItem key={p.id} value={p.id}>
                                            {p.sku} — {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Quantity received ({products.find((p) => p.id === productId)?.stock_unit ?? 'unit'})</Label>
                            <Input value={quantity} onChange={(e) => setQuantity(e.target.value)} required />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Unit cost (BDT)</Label>
                            <Input value={unitCost} onChange={(e) => setUnitCost(e.target.value)} required />
                        </div>
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label>Batch/lot no. (optional, for perishables)</Label>
                            <Input value={batchNo} onChange={(e) => setBatchNo(e.target.value)} />
                        </div>
                    </div>
                    {error && <div className="border-destructive/50 bg-destructive/10 text-destructive mt-4 rounded-md border p-3 text-sm">{error}</div>}
                    {result && <div className="mt-4 rounded-md border border-green-600/50 bg-green-600/10 p-3 text-sm text-green-700">{result}</div>}
                    <Button type="submit" className="mt-4" disabled={busy || !supplierId || !locationId || !productId}>
                        {busy ? 'Posting…' : 'Post Goods Receipt'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
