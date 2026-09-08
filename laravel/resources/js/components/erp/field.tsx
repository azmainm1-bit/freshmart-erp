import { ReactNode, useId } from 'react';
export function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: ReactNode }) {
    const id = useId();
    return <label className="grid content-start gap-1.5 text-sm font-medium" aria-describedby={error ? id : undefined}><span>{label}</span>{children}{hint && <span className="text-muted-foreground text-xs font-normal">{hint}</span>}{error && <span id={id} className="text-destructive text-xs font-normal">{error}</span>}</label>;
}
