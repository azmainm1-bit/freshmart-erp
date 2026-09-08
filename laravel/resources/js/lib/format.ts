export function money(value: string | number | null | undefined): string {
    return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 2 }).format(Number(value ?? 0));
}
export function quantity(value: string | number | null | undefined): string {
    return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 3 }).format(Number(value ?? 0));
}
export function date(value: string | null | undefined, time = false): string {
    if (!value) return '—';
    const normalized = /^\d{4}-\d\d-\d\d$/.test(value) ? value + 'T12:00:00Z' : value.includes('T') ? value : value.replace(' ', 'T') + 'Z';
    return new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', day: 'numeric', month: 'short', year: 'numeric', ...(time ? { hour: '2-digit', minute: '2-digit' } : {}) }).format(new Date(normalized));
}
export function today(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Dhaka', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
export function title(value: string): string { return value.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase()); }
