import { api } from '@/lib/http';
import { money, quantity } from '@/lib/format';
import { Product } from '@/types/erp';
import { LoaderCircle, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

export function ProductPicker({ onSelect, disabled = false }: { onSelect: (product: Product) => void; disabled?: boolean }) {
    const [search, setSearch] = useState(''); const [products, setProducts] = useState<Product[]>([]); const [loading, setLoading] = useState(false); const [error, setError] = useState('');
    useEffect(() => {
        if (!search.trim()) { setProducts([]); return; }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true); setError('');
            api.get<{ products: Product[] }>(`/api/products?q=${encodeURIComponent(search)}`, controller.signal).then(r => setProducts(r.products)).catch(e => { if (e.name !== 'AbortError') setError(e.message); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, 200);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [search]);
    return <div className="relative"><label className="mb-1.5 block text-sm font-medium" htmlFor="product-search">Find a product</label><div className="relative"><Search className="text-muted-foreground absolute top-3 left-3 size-4" /><input id="product-search" disabled={disabled} className="erp-input pl-9" placeholder="Type a product name, SKU or barcode…" value={search} onChange={e => setSearch(e.target.value)} />{loading && <LoaderCircle className="absolute top-3 right-3 size-4 animate-spin" />}</div>{error && <p role="alert" className="text-destructive mt-2 text-sm">{error}</p>}{search && !loading && <div className="bg-card mt-2 max-h-64 overflow-y-auto rounded-lg border shadow-sm">{products.map(product => <button type="button" disabled={disabled} className="hover:bg-muted flex w-full items-center justify-between gap-3 border-b p-3 text-left text-sm last:border-0" key={product.id} onClick={() => { onSelect(product); setSearch(''); setProducts([]); }}><span><span className="block font-medium">{product.name}</span><span className="text-muted-foreground text-xs">{product.sku} · {quantity(product.available)} {product.stock_unit} available</span></span><span className="text-xs tabular-nums">{money(product.selling_price)}</span></button>)}{!products.length && <p className="text-muted-foreground p-4 text-sm">No matching products.</p>}</div>}</div>;
}
