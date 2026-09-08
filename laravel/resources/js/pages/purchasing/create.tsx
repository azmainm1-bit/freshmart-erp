import { Field } from '@/components/erp/field';
import { Feedback, Page, Panel, Submit, usePermissions } from '@/components/erp/page';
import { ProductPicker } from '@/components/erp/product-picker';
import { ErpSelect } from '@/components/erp/select';
import { Button } from '@/components/ui/button';
import { useTransaction } from '@/hooks/use-transaction';
import { api, newIdempotencyKey } from '@/lib/http';
import { Location, Option, Product, PurchaseOrder } from '@/types/erp';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Line = { product: Product; quantity: string; unit_cost: string; batch_no: string; expiry_date: string };
export default function PurchaseCreate({ suppliers, locations, order }: { suppliers: Option[]; locations: Location[]; order: PurchaseOrder | null }) {
    const can = usePermissions(); const tx = useTransaction(`receiving:${order?.id ?? 'new'}`); const [mode, setMode] = useState('receive');
    const [supplier, setSupplier] = useState(order?.supplier_id ?? ''); const [location, setLocation] = useState(order?.location_id ?? locations[0]?.id ?? '');
    const [reference, setReference] = useState(''); const [expected, setExpected] = useState(''); const [notes, setNotes] = useState(''); const [cancelError, setCancelError] = useState(''); const [cancelling, setCancelling] = useState(false);
    const [lines, setLines] = useState<Line[]>(order?.lines.filter(l => Number(l.quantity) > Number(l.received_quantity)).map(l => ({ product: l.product, quantity: String(Number(l.quantity) - Number(l.received_quantity)), unit_cost: l.unit_cost, batch_no: '', expiry_date: '' })) ?? []);
    const disabled = tx.busy || tx.uncertain;
    function change(index: number, key: keyof Omit<Line,'product'>, value: string) { setLines(lines.map((line, i) => i === index ? { ...line, [key]: value } : line)); }
    async function submit(e: FormEvent) {
        e.preventDefault();
        const body = { supplier_id: supplier, location_id: location, notes: notes || null, ...(mode === 'order' ? { expected_on: expected || null } : { supplier_reference: reference || null, purchase_order_id: order?.id ?? null }), lines: lines.map(l => ({ product_id: l.product.id, [mode === 'order' ? 'quantity' : 'quantity_received']: l.quantity, unit_cost: l.unit_cost, ...(mode === 'receive' ? { batch_no: l.batch_no || null, expiry_date: l.expiry_date || null } : {}) })) };
        const result = await tx.send<{ goods_receipt?: { id: string }; order?: { id: string } }>(mode === 'order' ? '/api/purchase-orders' : '/api/goods-receipts', body);
        if (result) router.visit(result.goods_receipt ? `/purchasing/receipts/${result.goods_receipt.id}` : '/purchasing');
    }
    async function cancel() { if (!order) return; setCancelling(true); try { await api.post(`/api/purchase-orders/${order.id}/cancel`, {}, newIdempotencyKey()); router.visit('/purchasing'); } catch (e) { setCancelError(e instanceof Error ? e.message : 'Cancellation failed.'); } finally { setCancelling(false); } }
    return <Page title={order ? `Receive ${order.number}` : 'Order & receive goods'} description="Quantities are entered in stock units. Receiving posts inventory and creates the supplier balance." actions={order?.status === 'ordered' && can('purchasing.manage') && <Button variant="outline" disabled={cancelling} onClick={cancel}>Cancel unreceived order</Button>}>
        {cancelError && <p role="alert" className="text-destructive">{cancelError}</p>}
        <Panel><form onSubmit={submit} className="space-y-6 p-5"><fieldset disabled={disabled} className="space-y-6">
            {!order && can('purchasing.manage') && <div className="flex gap-2"><Button type="button" variant={mode === 'receive' ? 'default' : 'outline'} onClick={() => setMode('receive')}>Receive goods now</Button><Button type="button" variant={mode === 'order' ? 'default' : 'outline'} onClick={() => setMode('order')}>Create purchase order</Button></div>}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"><Field label="Supplier"><ErpSelect disabled={!!order} value={supplier} onChange={setSupplier} placeholder="Select supplier" options={suppliers.map(s => ({ value: s.id, label: s.name }))} /></Field><Field label="Receiving location"><ErpSelect disabled={!!order} value={location} onChange={setLocation} placeholder="Choose location" options={locations.map(l => ({ value: l.id, label: l.name }))} /></Field>{mode === 'receive' ? <Field label="Supplier invoice reference"><input className="erp-input" value={reference} onChange={e => setReference(e.target.value)} maxLength={255} placeholder="Supplier's invoice number" /></Field> : <Field label="Expected delivery"><input className="erp-input" type="date" value={expected} onChange={e => setExpected(e.target.value)} /></Field>}<Field label="Notes"><input className="erp-input" value={notes} onChange={e => setNotes(e.target.value)} maxLength={2000} /></Field></div>
            {!order && <ProductPicker onSelect={product => setLines(current => current.some(l => l.product.id === product.id) ? current : [...current, { product, quantity: '1', unit_cost: '', batch_no: '', expiry_date: '' }])} />}
            <div className="space-y-3">{!lines.length && <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">Search and add products to this document.</div>}{lines.map((line, i) => <div className="grid gap-3 rounded-lg border p-4 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto]" key={line.product.id}><div className="self-center"><p className="text-sm font-semibold">{line.product.name}</p><p className="text-muted-foreground text-xs">{line.product.sku} · {line.product.stock_unit}</p></div><Field label={`Quantity (${line.product.stock_unit})`}><input className="erp-input" type="number" min={line.product.is_weighted ? '.001' : '1'} step={line.product.is_weighted ? '.001' : '1'} required value={line.quantity} onChange={e => change(i,'quantity',e.target.value)} /></Field><Field label="Cost per stock unit"><input className="erp-input" type="number" min="0" step=".000001" required value={line.unit_cost} onChange={e => change(i,'unit_cost',e.target.value)} /></Field>{mode === 'receive' && <><Field label={line.product.is_batch_tracked ? 'Batch number *' : 'Batch number'}><input className="erp-input" required={line.product.is_batch_tracked} value={line.batch_no} onChange={e => change(i,'batch_no',e.target.value)} /></Field><Field label="Expiry date"><input className="erp-input" type="date" value={line.expiry_date} onChange={e => change(i,'expiry_date',e.target.value)} /></Field></>}<button aria-label={`Remove ${line.product.name}`} type="button" className="text-muted-foreground self-end p-2" onClick={() => setLines(lines.filter((_, j) => i !== j))}><Trash2 className="size-4" /></button></div>)}</div>
        </fieldset><Feedback {...tx} /><Submit {...tx}>{mode === 'order' ? 'Create purchase order' : 'Post goods receipt'}</Submit></form></Panel>
    </Page>;
}
