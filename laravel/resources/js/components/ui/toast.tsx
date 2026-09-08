import * as ToastPrimitives from '@radix-ui/react-toast';
import { cva, type VariantProps } from 'class-variance-authority';
import { CircleCheck, X } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const ToastProvider = ToastPrimitives.Provider;

const ToastViewport = React.forwardRef<React.ElementRef<typeof ToastPrimitives.Viewport>, React.ComponentPropsWithoutRef<typeof ToastPrimitives.Viewport>>(
    ({ className, ...props }, ref) => (
        <ToastPrimitives.Viewport
            ref={ref}
            className={cn('fixed top-0 right-0 z-100 flex max-h-screen w-full flex-col-reverse gap-2 p-4 sm:top-4 sm:right-4 sm:max-w-[420px]', className)}
            {...props}
        />
    ),
);
ToastViewport.displayName = ToastPrimitives.Viewport.displayName;

const toastVariants = cva(
    'group data-swipe-end:translate-x-(--radix-toast-swipe-end-x) data-swipe-move:translate-x-(--radix-toast-swipe-move-x) pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-xl border p-4 shadow-lg transition-all data-swipe-cancel:translate-x-0 data-swipe-move:transition-none data-[state=closed]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:animate-in data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full',
    {
        variants: {
            variant: {
                default: 'bg-card text-card-foreground',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
                destructive: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

const Toast = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Root>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Root> & VariantProps<typeof toastVariants>
>(({ className, variant, ...props }, ref) => {
    return <ToastPrimitives.Root ref={ref} className={cn(toastVariants({ variant }), className)} {...props} />;
});
Toast.displayName = ToastPrimitives.Root.displayName;

const ToastAction = React.forwardRef<React.ElementRef<typeof ToastPrimitives.Action>, React.ComponentPropsWithoutRef<typeof ToastPrimitives.Action>>(
    ({ className, ...props }, ref) => (
        <ToastPrimitives.Action ref={ref} className={cn('text-sm font-medium underline-offset-4 hover:underline', className)} {...props} />
    ),
);
ToastAction.displayName = ToastPrimitives.Action.displayName;

const ToastClose = React.forwardRef<React.ElementRef<typeof ToastPrimitives.Close>, React.ComponentPropsWithoutRef<typeof ToastPrimitives.Close>>(
    ({ className, ...props }, ref) => (
        <ToastPrimitives.Close
            ref={ref}
            className={cn('text-foreground/50 hover:text-foreground absolute top-3 right-3 rounded-md opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100 focus:outline-hidden', className)}
            toast-close=""
            {...props}
        >
            <X className="size-4" />
        </ToastPrimitives.Close>
    ),
);
ToastClose.displayName = ToastPrimitives.Close.displayName;

const ToastTitle = React.forwardRef<React.ElementRef<typeof ToastPrimitives.Title>, React.ComponentPropsWithoutRef<typeof ToastPrimitives.Title>>(
    ({ className, ...props }, ref) => <ToastPrimitives.Title ref={ref} className={cn('text-sm font-semibold', className)} {...props} />,
);
ToastTitle.displayName = ToastPrimitives.Title.displayName;

const ToastDescription = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Description>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Description>
>(({ className, ...props }, ref) => <ToastPrimitives.Description ref={ref} className={cn('mt-1 text-sm opacity-90', className)} {...props} />);
ToastDescription.displayName = ToastPrimitives.Description.displayName;

function ToastSuccessIcon() {
    return <CircleCheck className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />;
}

type ToastProps = React.ComponentPropsWithoutRef<typeof Toast>;
type ToastActionElement = React.ReactElement<typeof ToastAction>;

export {
    Toast,
    ToastAction,
    ToastClose,
    ToastDescription,
    ToastProvider,
    ToastSuccessIcon,
    ToastTitle,
    ToastViewport,
    type ToastActionElement,
    type ToastProps,
};
