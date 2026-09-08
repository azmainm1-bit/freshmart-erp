import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ShoppingBasket } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { erp } = usePage<SharedData>().props;
    return (
        <div className="bg-muted/40 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-3">
                        <Link href={route('home')} className="flex flex-col items-center gap-3 font-medium">
                            <span className="flex size-11 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                                <ShoppingBasket className="size-6" />
                            </span>
                            <span className="text-sm font-semibold tracking-tight">{erp?.name ?? 'FreshMart'}</span>
                        </Link>

                        <div className="space-y-1.5 text-center">
                            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    <div className="bg-card rounded-xl border p-6 shadow-sm">{children}</div>
                </div>
            </div>
        </div>
    );
}
