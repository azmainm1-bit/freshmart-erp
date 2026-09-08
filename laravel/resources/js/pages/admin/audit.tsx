import { Page, Panel } from '@/components/erp/page';
import { Pagination, Table } from '@/components/erp/table';
import { date, title } from '@/lib/format';
import { Option, PageData } from '@/types/erp';
type Entry = { id: string; action: string; actor: Option; created_at: string; auditable_type: string | null; auditable_id: string | null; metadata: Record<string,unknown> | null };
export default function Audit({ entries }: { entries: PageData<Entry> }) {
    return <Page title="Audit trail" description="Attributable history of posted transactions and administrative changes."><Panel><Table rows={entries.data} columns={[{ label: 'When', render: e => date(e.created_at,true) }, { label: 'Staff member', render: e => e.actor.name }, { label: 'Action', render: e => title(e.action.toLowerCase()) }, { label: 'Record', render: e => <div className="max-w-xs break-all text-xs">{e.auditable_type?.split('\\').pop()}<p className="text-muted-foreground">{e.auditable_id}</p></div> }, { label: 'Details', render: e => e.metadata && Object.keys(e.metadata).length ? <details><summary className="text-primary cursor-pointer text-xs">View details</summary><pre className="bg-muted mt-2 max-w-xs overflow-auto rounded p-2 text-xs">{JSON.stringify(e.metadata,null,2)}</pre></details> : '—' }]} /><Pagination page={entries} /></Panel></Page>;
}
