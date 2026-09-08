import * as React from 'react';

import type { ToastActionElement, ToastProps } from '@/components/ui/toast';

const TOAST_LIMIT = 3;
const TOAST_REMOVE_DELAY = 4000;

type ToasterToast = ToastProps & {
    id: string;
    title?: React.ReactNode;
    description?: React.ReactNode;
    action?: ToastActionElement;
};

type State = { toasts: ToasterToast[] };

let count = 0;
function genId() {
    count = (count + 1) % Number.MAX_SAFE_INTEGER;
    return count.toString();
}

const listeners: Array<(state: State) => void> = [];
let memoryState: State = { toasts: [] };

function dispatch(toasts: ToasterToast[]) {
    memoryState = { toasts };
    listeners.forEach((listener) => listener(memoryState));
}

function scheduleRemoval(id: string) {
    setTimeout(() => dispatch(memoryState.toasts.filter((t) => t.id !== id)), TOAST_REMOVE_DELAY);
}

type Toast = Omit<ToasterToast, 'id'>;

function toast({ ...props }: Toast) {
    const id = genId();
    const update = (next: Partial<ToasterToast>) => dispatch(memoryState.toasts.map((t) => (t.id === id ? { ...t, ...next } : t)));
    const dismiss = () => dispatch(memoryState.toasts.map((t) => (t.id === id ? { ...t, open: false } : t)));

    dispatch(
        [
            { ...props, id, open: true, onOpenChange: (open: boolean) => !open && dismiss() },
            ...memoryState.toasts,
        ].slice(0, TOAST_LIMIT),
    );
    scheduleRemoval(id);

    return { id, dismiss, update };
}

function useToast() {
    const [state, setState] = React.useState<State>(memoryState);

    React.useEffect(() => {
        listeners.push(setState);
        return () => {
            const index = listeners.indexOf(setState);
            if (index > -1) listeners.splice(index, 1);
        };
    }, []);

    return { ...state, toast };
}

export { toast, useToast };
