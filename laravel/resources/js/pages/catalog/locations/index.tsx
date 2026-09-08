import { Field } from '@/components/erp/field';
import { Page, Panel } from '@/components/erp/page';
import { ErpSelect } from '@/components/erp/select';
import { Table } from '@/components/erp/table';
import { Button } from '@/components/ui/button';
import { title } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const TYPES = ['stockroom', 'sales_floor', 'quarantine'] as const;

interface Location {
    id: string;
    name: string;
    type: string;
}

export default function LocationsIndex({ locations }: { locations: Location[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', type: 'stockroom' as string });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/catalog/locations', { onSuccess: () => reset('name') });
    };

    return (
        <Page title="Locations" description="Physical stock locations within your store — sales floor, stockroom and quarantine.">
            <Panel title="New location">
                <form onSubmit={submit} className="grid gap-4 p-5 sm:grid-cols-3 sm:items-end">
                    <Field label="Name" error={errors.name}><input className="erp-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
                    <Field label="Type" error={errors.type}><ErpSelect value={data.type} onChange={(v) => setData('type', v)} options={TYPES.map((t) => ({ value: t, label: title(t) }))} /></Field>
                    <Button type="submit" disabled={processing}>Create location</Button>
                </form>
            </Panel>

            <Panel title="Locations">
                <Table
                    rows={locations}
                    empty="No locations yet"
                    columns={[
                        { label: 'Name', render: (l) => l.name },
                        { label: 'Type', render: (l) => title(l.type) },
                    ]}
                />
            </Panel>
        </Page>
    );
}
