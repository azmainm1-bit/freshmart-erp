import { ApiRequestError, api, newIdempotencyKey } from '@/lib/http';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

type Pending = { path: string; body: unknown; key: string };
export function useTransaction(scope: string) {
    const { auth } = usePage<SharedData>().props;
    const storageKey = `erp-pending:${auth.user.id}:${scope}`;
    const pending = useRef<Pending | null>(null);
    const [uncertain, setUncertain] = useState(() => {
        try { const saved = sessionStorage.getItem(storageKey); if (saved) { pending.current = JSON.parse(saved); return true; } } catch { /* Private browsing may disable storage. */ }
        return false;
    });
    const [busy, setBusy] = useState(false); const inFlight = useRef(false);
    const [error, setError] = useState(''); const [errors, setErrors] = useState<Record<string, string[]>>({});
    async function send<T>(path: string, body: unknown): Promise<T | null> {
        if (inFlight.current) return null;
        inFlight.current = true; setBusy(true); setError(''); setErrors({});
        const operation = pending.current ?? { path, body, key: newIdempotencyKey() };
        pending.current = operation;
        try { sessionStorage.setItem(storageKey, JSON.stringify(operation)); } catch { /* In-memory replay remains available. */ }
        try {
            const result = await api.post<T>(operation.path, operation.body, operation.key);
            pending.current = null; setUncertain(false); try { sessionStorage.removeItem(storageKey); } catch { /* optional persistence */ }
            return result;
        } catch (err) {
            const failure = err instanceof ApiRequestError ? err : new ApiRequestError(0, { error: 'UNKNOWN', message: 'The result is uncertain. Retry this same submission.' });
            setError(failure.message); setErrors(failure.body.details ?? {});
            const ambiguous = failure.status === 0 || failure.status >= 500;
            setUncertain(ambiguous);
            if (!ambiguous) { pending.current = null; try { sessionStorage.removeItem(storageKey); } catch { /* optional persistence */ } }
            return null;
        } finally { inFlight.current = false; setBusy(false); }
    }
    return { send, busy, uncertain, error, errors };
}
