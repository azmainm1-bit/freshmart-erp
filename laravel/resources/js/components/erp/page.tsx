import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { date } from '@/lib/format';
import { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDownRight, ArrowRight, ArrowUpRight, Inbox, LoaderCircle, LucideIcon } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

export function usePermissions() {
    const { auth } = usePage<SharedData>().props;
    return (permission: string) => (auth.permissions ?? []).includes(permission);
}
export function Page({ title, description, actions, children }: { title: string; description?: string; actions?: ReactNode; children: ReactNode }) {
    return (
        <AppLayout breadcrumbs={[{ title, href: '#' }]}>
            <Head title={title} />
            <main id="main-content" tabIndex={-1} className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-1 text-xs font-semibold tracking-[.15em] uppercase">Store workspace</p>
                        <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">{title}</h1>
                        {description && <p className="text-muted-foreground mt-2 max-w-3xl text-sm">{description}</p>}
                    </div>
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
                {children}
            </main>
        </AppLayout>
    );
}
export function Panel({
    title,
    description,
    action,
    children,
    className = '',
}: {
    title?: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`bg-card overflow-hidden rounded-xl border shadow-sm ${className}`}>
            {title && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                    <div>
                        <h2 className="font-semibold">{title}</h2>
                        {description && <p className="text-muted-foreground mt-1 text-xs">{description}</p>}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}
export type MetricTone = 'neutral' | 'success' | 'info' | 'warning' | 'danger' | 'analytics';
const metricToneClasses: Record<MetricTone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400',
    info: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400',
    warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400',
    danger: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400',
    analytics: 'bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-400',
};
export function Metric({
    label,
    value,
    detail,
    tone,
    accent = false,
    icon: Icon,
    trend,
}: {
    label: string;
    value: ReactNode;
    detail?: string;
    tone?: MetricTone;
    accent?: boolean;
    icon?: LucideIcon;
    trend?: { direction: 'up' | 'down'; label: string };
}) {
    const resolvedTone = tone ?? (accent ? 'success' : 'neutral');
    return (
        <div className="bg-card rounded-xl border p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <p className="text-muted-foreground text-sm font-medium">{label}</p>
                {Icon && (
                    <span className={`rounded-lg p-2 ${metricToneClasses[resolvedTone]}`}>
                        <Icon className="size-4" />
                    </span>
                )}
            </div>
            <p className="mt-3 text-2xl font-semibold tracking-tight tabular-nums">{value}</p>
            {(detail || trend) && (
                <div className="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                    {trend && (
                        <span
                            className={`inline-flex items-center gap-0.5 font-medium ${trend.direction === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}
                        >
                            {trend.direction === 'up' ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
                            {trend.label}
                        </span>
                    )}
                    {detail && <span className="text-muted-foreground">{detail}</span>}
                </div>
            )}
        </div>
    );
}
export function Status({
    children,
    tone = 'neutral',
}: {
    children: ReactNode;
    tone?: 'good' | 'warning' | 'danger' | 'neutral' | 'info' | 'analytics';
}) {
    const colors = {
        good: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        warning: 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        danger: 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
        info: 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
        analytics: 'bg-violet-50 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
        neutral: 'bg-muted text-muted-foreground',
    };
    return <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${colors[tone]}`}>{children}</span>;
}
export function Empty({
    title = 'No records yet',
    description = 'Records will appear here as you use this workflow.',
}: {
    title?: string;
    description?: string;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-12 text-center">
            <div className="bg-muted mb-4 rounded-full p-3">
                <Inbox className="text-muted-foreground size-6" />
            </div>
            <p className="font-medium">{title}</p>
            <p className="text-muted-foreground mt-1 max-w-sm text-sm">{description}</p>
        </div>
    );
}
export function ActionLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Button asChild>
            <Link href={href}>
                {children}
                <ArrowRight className="size-4" />
            </Link>
        </Button>
    );
}
export function Submit({ busy, uncertain = false, children = 'Save' }: { busy: boolean; uncertain?: boolean; children?: ReactNode }) {
    return (
        <Button type="submit" disabled={busy}>
            {busy && <LoaderCircle className="size-4 animate-spin" />}
            {busy ? 'Saving…' : uncertain ? 'Retry original submission' : children}
        </Button>
    );
}
export function Feedback({ error, errors, uncertain }: { error: string; errors: Record<string, string[]>; uncertain?: boolean }) {
    return (
        <>
            {uncertain && (
                <div role="status" className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    A previous submission needs confirmation. Retry to retrieve its result safely; the original submitted values will be used.
                </div>
            )}
            {error && (
                <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">
                    <p className="font-medium">{error}</p>
                    {Object.entries(errors).length > 0 && (
                        <ul className="mt-2 list-inside list-disc">
                            {Object.entries(errors).map(([key, messages]) => (
                                <li key={key}>{Array.isArray(messages) ? messages.join(' ') : String(messages)}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </>
    );
}
export function Filters({
    path,
    initial,
    dates = true,
    search = true,
    children,
}: {
    path: string;
    initial: Record<string, string | boolean>;
    dates?: boolean;
    search?: boolean;
    children?: ReactNode;
}) {
    const [data, setData] = useState(initial);
    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(path, data, { preserveState: true, preserveScroll: true });
    }
    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            {search && (
                <label className="min-w-48 flex-1 text-xs font-medium">
                    Search
                    <input
                        className="erp-input mt-1"
                        placeholder="Search records…"
                        value={String(data.q ?? '')}
                        onChange={(e) => setData({ ...data, q: e.target.value })}
                    />
                </label>
            )}
            {dates && (
                <>
                    <label className="text-xs font-medium">
                        From
                        <input
                            type="date"
                            className="erp-input mt-1"
                            value={String(data.from ?? '')}
                            onChange={(e) => setData({ ...data, from: e.target.value })}
                        />
                    </label>
                    <label className="text-xs font-medium">
                        To
                        <input
                            type="date"
                            className="erp-input mt-1"
                            value={String(data.to ?? '')}
                            onChange={(e) => setData({ ...data, to: e.target.value })}
                        />
                    </label>
                </>
            )}
            {children}
            <Button type="submit" variant="secondary">
                Apply filters
            </Button>
        </form>
    );
}
export function DateText({ value }: { value: string }) {
    return <span className="whitespace-nowrap">{date(value)}</span>;
}
