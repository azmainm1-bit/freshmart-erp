import { Toast, ToastClose, ToastDescription, ToastProvider, ToastSuccessIcon, ToastTitle, ToastViewport } from '@/components/ui/toast';
import { useToast } from '@/hooks/use-toast';

export function Toaster() {
    const { toasts } = useToast();

    return (
        <ToastProvider>
            {toasts.map(({ id, title, description, action, variant, ...props }) => (
                <Toast key={id} variant={variant} {...props}>
                    {variant === 'success' && <ToastSuccessIcon />}
                    <div className="grid flex-1 gap-1">
                        {title && <ToastTitle>{title}</ToastTitle>}
                        {description && <ToastDescription>{description}</ToastDescription>}
                    </div>
                    {action}
                    <ToastClose />
                </Toast>
            ))}
            <ToastViewport />
        </ToastProvider>
    );
}
