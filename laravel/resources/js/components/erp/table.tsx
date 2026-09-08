import { Button } from '@/components/ui/button';
import { PageData } from '@/types/erp';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { ReactNode } from 'react';
import { Empty } from './page';

export type Column<T> = { label: string; render: (row: T) => ReactNode; className?: string };
export function Table<T extends { id: string }>({ rows, columns, empty }: { rows: T[]; columns: Column<T>[]; empty?: string }) {
    if (!rows.length) return <Empty title={empty ?? 'No matching records'} description="Try a different search or date range, or create your first record." />;
    return <div className="w-full overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-muted/50 text-muted-foreground border-b text-xs"><tr>{columns.map((c, i) => <th key={i} scope="col" className={`whitespace-nowrap px-5 py-3 font-medium ${c.className ?? ''}`}>{c.label}</th>)}</tr></thead><tbody className="divide-y">{rows.map(row => <tr key={row.id} className="hover:bg-muted/30 transition-colors">{columns.map((c, i) => <td key={i} className={`px-5 py-4 align-middle ${c.className ?? ''}`}>{c.render(row)}</td>)}</tr>)}</tbody></table></div>;
}
export function Pagination<T>({ page }: { page: PageData<T> }) {
    return <nav aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3"><p className="text-muted-foreground text-xs">{page.total ? `${page.from}–${page.to} of ${page.total} records` : '0 records'}</p><div className="flex items-center gap-2">{page.prev_page_url ? <Button variant="outline" size="sm" asChild><Link href={page.prev_page_url} preserveScroll><ChevronLeft className="size-4" />Previous</Link></Button> : <Button variant="outline" size="sm" disabled>Previous</Button>}<span className="text-muted-foreground px-1 text-xs">{page.current_page} / {page.last_page}</span>{page.next_page_url ? <Button variant="outline" size="sm" asChild><Link href={page.next_page_url} preserveScroll>Next<ChevronRight className="size-4" /></Link></Button> : <Button variant="outline" size="sm" disabled>Next</Button>}</div></nav>;
}
