import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/toaster';
import { toast } from '@/hooks/use-toast';
import { BreadcrumbItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect } from 'react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const { auth } = usePage<SharedData>().props;
    return <header className="bg-card/95 flex h-16 shrink-0 items-center justify-between gap-3 border-b px-4 md:px-6"><div className="flex min-w-0 items-center gap-3"><SidebarTrigger /><Breadcrumbs breadcrumbs={breadcrumbs} /></div><div className="text-muted-foreground hidden items-center gap-2 text-xs sm:flex"><CircleCheck className="size-3.5 text-emerald-600" /><span className="capitalize">{auth.role ?? 'Staff'} workspace</span></div></header>;
}
export function FlashNotice() {
    const { flash } = usePage<SharedData>().props;
    useEffect(() => { if (flash?.success) toast({ variant: 'success', title: flash.success }); }, [flash?.success]);
    return <Toaster />;
}
