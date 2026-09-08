import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Product {
    id: string;
    sku: string;
    name: string;
    stock_unit: string;
}

interface Balance {
    id: string;
    quantity_on_hand: string;
    average_cost: string;
    location: { name: string; type: string };
    batch: { batch_no: string } | null;
}

interface Movement {
    id: string;
    quantity_delta: string;
    unit_cost: string;
    movement_type: string;
    created_at: string;
    location: { name: string };
    batch: { batch_no: string } | null;
    actor: { name: string; username: string };
}

export default function StockShow({ product, balances, movements }: { product: Product; balances: Balance[]; movements: Movement[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Products', href: '/catalog/products' },
        { title: `Stock — ${product.name}`, href: `/inventory/stock/${product.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stock — ${product.name}`} />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">
                    {product.sku} — {product.name}
                </h1>

                <section className="rounded-xl border" aria-labelledby="balances-heading">
                    <h2 id="balances-heading" className="border-b p-4 text-lg font-semibold">
                        Balances by location
                    </h2>
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground border-b text-left">
                            <tr>
                                <th className="p-3 font-medium">Location</th>
                                <th className="p-3 font-medium">Batch</th>
                                <th className="p-3 font-medium">Qty on hand</th>
                                <th className="p-3 font-medium">Avg. cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            {balances.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground p-4 text-center">
                                        No stock recorded yet.
                                    </td>
                                </tr>
                            )}
                            {balances.map((b) => (
                                <tr key={b.id} className="border-b last:border-0">
                                    <td className="p-3">{b.location.name}</td>
                                    <td className="p-3">{b.batch?.batch_no ?? '—'}</td>
                                    <td className="p-3">
                                        {b.quantity_on_hand} {product.stock_unit}
                                    </td>
                                    <td className="p-3">{b.average_cost} BDT</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="rounded-xl border" aria-labelledby="movements-heading">
                    <h2 id="movements-heading" className="border-b p-4 text-lg font-semibold">
                        Movement history
                    </h2>
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground border-b text-left">
                            <tr>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Location</th>
                                <th className="p-3 font-medium">Qty</th>
                                <th className="p-3 font-medium">Unit cost</th>
                                <th className="p-3 font-medium">Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.map((m) => (
                                <tr key={m.id} className="border-b last:border-0">
                                    <td className="p-3">{new Date(m.created_at).toLocaleString()}</td>
                                    <td className="p-3 capitalize">{m.movement_type.replace('_', ' ')}</td>
                                    <td className="p-3">{m.location.name}</td>
                                    <td className="p-3">{m.quantity_delta}</td>
                                    <td className="p-3">{m.unit_cost}</td>
                                    <td className="p-3">{m.actor.name}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </AppLayout>
    );
}
