import { Field } from '@/components/erp/field';
import { Feedback, Page, Panel, Status, Submit, usePermissions } from '@/components/erp/page';
import { ErpSelect } from '@/components/erp/select';
import { Button } from '@/components/ui/button';
import { useTransaction } from '@/hooks/use-transaction';
import { money, quantity } from '@/lib/format';
import { api } from '@/lib/http';
import { decimal, lineTotal, scaled } from '@/lib/pricing';
import { Location, Option, Product, Shift } from '@/types/erp';
import { Link, router } from '@inertiajs/react';
import { Barcode, CheckCircle2, CreditCard, LoaderCircle, Minus, Plus, Search, ShoppingBasket, Trash2, X } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type CartLine = { product: Product; quantity: string; discount: string };
type Tender = { method: string; amount: string; reference: string };

export default function PointOfSale({ shift, expectedCash, locations, categories }: { shift: Shift | null; expectedCash: string | null; locations: Location[]; categories: string[] }) {
    const can = usePermissions();
    const [search, setSearch] = useState(''); const [category, setCategory] = useState('');
    const [products, setProducts] = useState<Product[]>([]); const [loading, setLoading] = useState(false); const [lookupError, setLookupError] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]); const [payments, setPayments] = useState<Tender[]>([{ method: 'cash', amount: '', reference: '' }]);
    const [customer, setCustomer] = useState<Option | null>(null); const [notes, setNotes] = useState('');
    const [receipt, setReceipt] = useState<{ id: string; number: string; change_amount: string } | null>(null);
    const checkout = useTransaction('checkout'); const searchRef = useRef<HTMLInputElement>(null); const tenderRef = useRef<HTMLInputElement>(null);
    const disabled = checkout.busy || checkout.uncertain;
    const total = cart.reduce((sum, line) => sum + lineTotal(line.product.selling_price, line.quantity, line.product.tax_rate_percent, line.product.tax_inclusive, line.discount), 0n);
    const tendered = payments.reduce((sum, p) => sum + scaled(p.amount, 2), 0n);

    useEffect(() => {
        if (!shift) return;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true); setLookupError('');
            const params = new URLSearchParams({ q: search, category, location_id: shift.location_id });
            api.get<{ products: Product[] }>(`/api/products?${params}`, controller.signal).then(r => setProducts(r.products)).catch(e => { if (e.name !== 'AbortError') setLookupError(e.message); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, 150);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [search, category, shift, receipt]);
    useEffect(() => {
        const handler = (e: KeyboardEvent) => { if (e.key === 'F2') { e.preventDefault(); searchRef.current?.focus(); } if (e.key === 'F8') { e.preventDefault(); tenderRef.current?.focus(); } };
        window.addEventListener('keydown', handler); return () => window.removeEventListener('keydown', handler);
    }, []);

    function add(product: Product) {
        if (disabled) return;
        setCart(current => {
            const found = current.find(l => l.product.id === product.id);
            return found ? current.map(l => l.product.id === product.id ? { ...l, quantity: decimal(scaled(l.quantity, 3) + 1000n, 3) } : l) : [...current, { product, quantity: '1', discount: '0' }];
        });
        setSearch(''); searchRef.current?.focus(); setReceipt(null);
    }
    async function scan(e: FormEvent) {
        e.preventDefault(); if (!shift || !search.trim()) return;
        try {
            const result = await api.get<{ products: Product[] }>(`/api/products?q=${encodeURIComponent(search)}&location_id=${shift.location_id}`);
            const product = result.products.find(p => p.sku === search || p.barcodes.some(b => b.code === search)) ?? (result.products.length === 1 ? result.products[0] : null);
            if (product && Number(product.available) > 0) add(product); else setLookupError(product ? 'This product has no saleable stock at this till.' : 'Select the matching product below.');
        } catch (e) { setLookupError(e instanceof Error ? e.message : 'Product lookup failed.'); }
    }
    async function pay(e: FormEvent) {
        e.preventDefault(); if (!shift) return;
        const result = await checkout.send<{ sale: { id: string; number: string; change_amount: string } }>('/api/sales', {
            shift_id: shift.id, customer_id: customer?.id ?? null, expected_total: decimal(total), notes: notes || null,
            lines: cart.map(l => ({ product_id: l.product.id, quantity: l.quantity, discount_amount: l.discount || '0' })),
            payments: payments.filter(p => scaled(p.amount, 2) > 0n).map(p => ({ ...p, reference: p.reference || null })),
        });
        if (result) { setReceipt(result.sale); setCart([]); setPayments([{ method: 'cash', amount: '', reference: '' }]); setCustomer(null); setNotes(''); router.reload({ only: ['expectedCash'] }); searchRef.current?.focus(); }
    }

    return <Page title="Point of sale" description="Scan, add, take payment. F2 focuses product search; F8 focuses the first payment amount." actions={shift && <Status tone="good">{shift.counter} · Shift open</Status>}>
        {!shift ? <OpenShift locations={locations} /> : <>
            {receipt && <div role="status" className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-950"><div className="flex items-center gap-3"><CheckCircle2 /><div><p className="font-semibold">Sale complete · {receipt.number}</p><p className="text-sm">Give change: {money(receipt.change_amount)}</p></div></div><Button asChild><Link href={`/sales/${receipt.id}`}>View / print receipt</Link></Button></div>}
            <div className="grid items-start gap-5 xl:grid-cols-[1fr_440px]">
                <Panel><div className="space-y-3 border-b p-4"><form onSubmit={scan} className="relative"><Search className="text-muted-foreground absolute top-3 left-3 size-5" /><input ref={searchRef} autoFocus disabled={disabled} className="erp-input py-3 pr-12 pl-10" placeholder="Scan barcode or search products (F2)" aria-label="Scan barcode or search products" value={search} onChange={e => setSearch(e.target.value)} /><Barcode className="text-muted-foreground absolute top-3 right-3 size-5" /></form><div className="flex gap-2 overflow-x-auto pb-1">{['', ...categories].map(c => <button disabled={disabled} key={c} onClick={() => setCategory(c)} className={`shrink-0 rounded-full px-3 py-1.5 text-xs font-medium ${c === category ? 'bg-emerald-700 text-white' : 'bg-muted text-muted-foreground hover:text-foreground'}`}>{c || 'All products'}</button>)}</div></div>
                    {lookupError && <p role="alert" className="text-destructive px-4 pt-4 text-sm">{lookupError}</p>}
                    <div className="grid max-h-[65vh] grid-cols-2 gap-3 overflow-y-auto p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-3 2xl:grid-cols-4" aria-busy={loading}>{loading && !products.length ? Array.from({ length: 8 }, (_, i) => <div key={i} className="bg-muted h-40 animate-pulse rounded-xl" />) : products.map(product => <button key={product.id} type="button" disabled={disabled || Number(product.available) <= 0} onClick={() => add(product)} className="hover:border-emerald-600 hover:bg-emerald-50/40 focus-visible:ring-ring flex min-h-40 flex-col rounded-xl border p-3 text-left transition-colors focus-visible:ring-2 disabled:opacity-40 dark:hover:bg-emerald-950/30"><div className="mb-3 flex w-full items-center justify-between"><span className="rounded-lg bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950"><ShoppingBasket className="size-5" /></span>{Number(product.available) <= 0 ? <Status tone="danger">Out of stock</Status> : <span className="text-muted-foreground text-[10px]">{quantity(product.available)} {product.stock_unit}</span>}</div><span className="line-clamp-2 text-sm font-medium">{product.name}</span><span className="text-muted-foreground mt-1 text-[10px]">{product.sku}</span><span className="mt-auto pt-3 text-sm font-semibold">{money(product.selling_price)}{!product.tax_inclusive && <span className="text-muted-foreground text-[10px]"> + tax</span>}</span></button>)}</div>{!loading && !products.length && <p className="text-muted-foreground px-4 pb-8 text-center text-sm">No products found. Try a name, SKU or barcode.</p>}
                </Panel>
                <Panel title="Current sale" description={`${cart.length} distinct products`} action={cart.length > 0 && <Button variant="ghost" size="sm" disabled={disabled} onClick={() => setCart([])}>Clear</Button>}><form onSubmit={pay} className="space-y-4 p-4"><fieldset disabled={disabled} className="space-y-4 disabled:opacity-60"><CustomerPicker selected={customer} onSelect={setCustomer} />
                    <div className="max-h-80 divide-y overflow-y-auto">{!cart.length && <div className="text-muted-foreground flex flex-col items-center py-10 text-sm"><ShoppingBasket className="mb-3 size-8 opacity-40" />Scan or select a product to start.</div>}{cart.map((line, index) => <div key={line.product.id} className="py-3"><div className="flex items-start justify-between gap-2"><p className="text-sm font-medium">{line.product.name}</p><button type="button" aria-label={`Remove ${line.product.name}`} onClick={() => setCart(c => c.filter((_, i) => i !== index))} className="text-muted-foreground hover:text-destructive p-1"><Trash2 className="size-4" /></button></div><div className="mt-2 flex flex-wrap items-center justify-between gap-2"><div className="flex items-center gap-1"><button type="button" aria-label="Decrease quantity" className="rounded border p-1.5" onClick={() => setCart(c => c.map((l, i) => i === index ? { ...l, quantity: decimal(scaled(l.quantity, 3) > 1000n ? scaled(l.quantity, 3) - 1000n : 1000n, 3) } : l))}><Minus className="size-3" /></button><input type="number" min={line.product.is_weighted ? '0.001' : '1'} step={line.product.is_weighted ? '0.001' : '1'} required className="erp-input w-20 px-2 py-1 text-center" aria-label={`${line.product.name} quantity`} value={line.quantity} onChange={e => setCart(c => c.map((l, i) => i === index ? { ...l, quantity: e.target.value } : l))} /><button type="button" aria-label="Increase quantity" className="rounded border p-1.5" onClick={() => add(line.product)}><Plus className="size-3" /></button><span className="text-muted-foreground ml-1 text-xs">{line.product.stock_unit}</span></div><span className="text-sm font-semibold tabular-nums">{money(decimal(lineTotal(line.product.selling_price, line.quantity, line.product.tax_rate_percent, line.product.tax_inclusive, line.discount)))}</span></div>{can('sales.discount') && <label className="text-muted-foreground mt-2 flex items-center gap-2 text-xs">Line discount (BDT)<input className="erp-input w-24 px-2 py-1" type="number" min="0" step="0.01" value={line.discount} onChange={e => setCart(c => c.map((l, i) => i === index ? { ...l, discount: e.target.value } : l))} /></label>}</div>)}</div>
                    <div className="flex items-center justify-between rounded-lg bg-emerald-950 p-4 text-white"><span className="text-sm">Total including tax</span><strong className="text-2xl tabular-nums">{money(decimal(total))}</strong></div>
                    <div className="space-y-3">{payments.map((payment, i) => <div key={i} className="grid grid-cols-[1fr_1fr_auto] gap-2"><label className="text-xs font-medium">Method<ErpSelect className="mt-1" value={payment.method} onChange={v => setPayments(p => p.map((val, j) => j === i ? { ...val, method: v } : val))} options={['cash', 'card', 'mobile', 'bank'].map(m => ({ value: m, label: m[0].toUpperCase() + m.slice(1) }))} /></label><label className="text-xs font-medium">Amount (BDT)<input ref={i === 0 ? tenderRef : undefined} type="number" min="0" step="0.01" className="erp-input mt-1" value={payment.amount} onChange={e => setPayments(p => p.map((v, j) => j === i ? { ...v, amount: e.target.value } : v))} /></label>{payments.length > 1 ? <button aria-label="Remove payment" type="button" className="self-end p-2" onClick={() => setPayments(p => p.filter((_, j) => i !== j))}><X className="size-4" /></button> : <Button type="button" variant="secondary" className="self-end" onClick={() => setPayments([{ ...payment, amount: decimal(total) }])}>Exact</Button>}{payment.method !== 'cash' && <label className="col-span-3 text-xs font-medium">Merchant transaction reference<input required className="erp-input mt-1" value={payment.reference} onChange={e => setPayments(p => p.map((v, j) => j === i ? { ...v, reference: e.target.value } : v))} /></label>}</div>)}{payments.length < 5 && <button type="button" className="text-primary text-xs font-medium" onClick={() => setPayments(p => [...p, { method: 'card', amount: '', reference: '' }])}>+ Split payment</button>}</div>
                    <div className="flex justify-between text-sm"><span className="text-muted-foreground">{tendered >= total ? 'Change to give' : 'Remaining / customer credit'}</span><span className="font-semibold tabular-nums">{money(decimal(tendered >= total ? tendered - total : total - tendered))}</span></div>
                    {can('sales.discount') && <Field label="Sale note / discount reason"><input className="erp-input" value={notes} onChange={e => setNotes(e.target.value)} maxLength={2000} /></Field>}
                </fieldset><Feedback {...checkout} /><Button type="submit" className="w-full py-6 text-base" disabled={checkout.busy || (!cart.length && !checkout.uncertain)}>{checkout.busy ? <LoaderCircle className="size-5 animate-spin" /> : <CreditCard className="size-5" />}{checkout.busy ? 'Posting sale…' : checkout.uncertain ? 'Retry original sale' : 'Complete sale'}</Button><p className="text-muted-foreground text-center text-[11px]">Card, bank and mobile payments record your confirmed merchant reference.</p></form></Panel>
            </div><ShiftClose shift={shift} expectedCash={expectedCash} />
        </>}
    </Page>;
}

function OpenShift({ locations }: { locations: Location[] }) {
    const tx = useTransaction('open-shift'); const [location, setLocation] = useState(locations[0]?.id ?? ''); const [counter, setCounter] = useState('Till 1'); const [cash, setCash] = useState('0');
    async function submit(e: FormEvent) { e.preventDefault(); if (await tx.send('/api/shifts', { location_id: location, counter, opening_cash: cash })) router.reload(); }
    return <Panel title="Open your till" description="Record the physical opening float before taking a payment." className="max-w-xl"><form onSubmit={submit} className="space-y-4 p-5"><fieldset disabled={tx.busy || tx.uncertain} className="grid gap-4"><Field label="Sales location"><ErpSelect value={location} onChange={setLocation} placeholder="Choose a location" options={locations.map(l => ({ value: l.id, label: l.name }))} /></Field><Field label="Counter"><input className="erp-input" value={counter} onChange={e => setCounter(e.target.value)} required maxLength={80} /></Field><Field label="Opening cash (BDT)"><input type="number" min="0" step="0.01" className="erp-input" value={cash} onChange={e => setCash(e.target.value)} required /></Field></fieldset><Feedback {...tx} /><Submit {...tx}>Open shift</Submit></form></Panel>;
}
function ShiftClose({ shift, expectedCash }: { shift: Shift; expectedCash: string | null }) {
    const tx = useTransaction(`close-shift:${shift.id}`); const [counted, setCounted] = useState(''); const [note, setNote] = useState('');
    async function submit(e: FormEvent) { e.preventDefault(); if (await tx.send(`/api/shifts/${shift.id}/close`, { counted_cash: counted, closing_note: note || null })) router.reload(); }
    return <details className="bg-card rounded-xl border p-4"><summary className="cursor-pointer text-sm font-medium">Till reconciliation · Expected cash {money(expectedCash)}</summary><form onSubmit={submit} className="mt-4 grid max-w-2xl gap-4 sm:grid-cols-2"><Field label="Physical cash counted (BDT)"><input disabled={tx.busy || tx.uncertain} className="erp-input" type="number" step="0.01" min="0" required value={counted} onChange={e => setCounted(e.target.value)} /></Field><Field label="Closing / variance note"><input disabled={tx.busy || tx.uncertain} className="erp-input" value={note} onChange={e => setNote(e.target.value)} /></Field><div className="sm:col-span-2"><Feedback {...tx} /></div><Submit {...tx}>Close and reconcile shift</Submit></form></details>;
}
function CustomerPicker({ selected, onSelect }: { selected: Option | null; onSelect: (customer: Option | null) => void }) {
    const [search, setSearch] = useState(''); const [options, setOptions] = useState<Option[]>([]); const [error, setError] = useState('');
    useEffect(() => { if (!search) { setOptions([]); return; } const controller = new AbortController(); const timer = setTimeout(() => api.get<{ customers: Option[] }>(`/api/customers?q=${encodeURIComponent(search)}`, controller.signal).then(r => { setOptions(r.customers); setError(''); }).catch(e => { if (e.name !== 'AbortError') setError(e.message); }), 200); return () => { controller.abort(); clearTimeout(timer); }; }, [search]);
    return <div>{selected ? <div className="flex items-center justify-between rounded-lg border p-2.5 text-sm"><span>{selected.name}</span><button type="button" aria-label="Use walk-in customer" onClick={() => onSelect(null)}><X className="size-4" /></button></div> : <input aria-label="Search customer" className="erp-input" placeholder="Walk-in customer · search name or phone" value={search} onChange={e => setSearch(e.target.value)} />}{error && <p className="text-destructive text-xs">{error}</p>}{options.length > 0 && !selected && <div className="mt-1 max-h-40 overflow-auto rounded border">{options.map(o => <button type="button" className="hover:bg-muted block w-full p-2 text-left text-sm" key={o.id} onClick={() => { onSelect(o); setSearch(''); }}>{o.name}</button>)}</div>}</div>;
}
