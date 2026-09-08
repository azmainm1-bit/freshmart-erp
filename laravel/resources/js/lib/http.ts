export interface ApiErrorBody { error: string; message: string; details?: Record<string, string[]>; }
export class ApiRequestError extends Error {
    constructor(public status: number, public body: ApiErrorBody) { super(body.message); }
}
export function newIdempotencyKey(): string {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return Array.from(crypto.getRandomValues(new Uint8Array(24)), b => b.toString(16).padStart(2, '0')).join('');
}
async function request<T>(method: string, path: string, body?: unknown, key?: string, signal?: AbortSignal): Promise<T> {
    const cookie = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie[1]) : '' };
    if (key) headers['Idempotency-Key'] = key;
    let response: Response;
    try { response = await fetch(path, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body), signal }); }
    catch (error) { if (error instanceof DOMException && error.name === 'AbortError') throw error; throw new ApiRequestError(0, { error: 'NETWORK', message: 'Connection interrupted. Retry this submission to check its result safely.' }); }
    const text = await response.text();
    let data: Record<string, unknown> = {};
    let validJson = false;
    try { data = text ? JSON.parse(text) : {}; validJson = Boolean(data && typeof data === 'object'); } catch { /* Normalize malformed gateway responses below. */ }
    if (!response.ok || !text || !validJson || response.headers.get('content-type')?.includes('text/html')) {
        const message = response.status === 419 ? 'Your session expired. Refresh the page and sign in again.' : response.status === 401 ? 'Sign in again to continue.' : response.status >= 500 ? 'The server could not confirm the result. Retry the same submission safely.' : String(data.message ?? 'This request could not be completed.');
        throw new ApiRequestError(response.ok ? 502 : response.status || 500, { error: String(data.error ?? 'REQUEST_FAILED'), message, details: (data.errors ?? data.details) as Record<string, string[]> | undefined });
    }
    return data as T;
}
export const api = {
    get: <T>(path: string, signal?: AbortSignal) => request<T>('GET', path, undefined, undefined, signal),
    post: <T>(path: string, body?: unknown, key?: string) => request<T>('POST', path, body, key),
};
