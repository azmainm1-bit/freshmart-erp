import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Boxes, ClipboardList, FolderTree, History, LayoutDashboard, MapPin, Package, PackagePlus, Receipt, RotateCcw, Settings2, ShoppingBasket, ShoppingCart, Truck, Users, Wallet } from 'lucide-react';

const groups = [
    { title: 'Workspace', items: [{ title: 'Overview', url: '/dashboard', icon: LayoutDashboard, permission: '' }, { title: 'Point of sale', url: '/pos', icon: ShoppingCart, permission: 'sales.create' }] },
    { title: 'Sales & people', items: [{ title: 'Sales history', url: '/sales', icon: Receipt, permission: 'sales' }, { title: 'Returns', url: '/returns', icon: RotateCcw, permission: 'sales.view-all' }, { title: 'Customers', url: '/customers', icon: Users, permission: 'customers.manage' }] },
    { title: 'Inventory & buying', items: [{ title: 'Stock overview', url: '/inventory', icon: Boxes, permission: 'inventory.view' }, { title: 'Products', url: '/catalog/products', icon: Package, permission: 'products.manage' }, { title: 'Categories', url: '/catalog/categories', icon: FolderTree, permission: 'products.manage' }, { title: 'Stock operations', url: '/inventory/operations', icon: History, permission: 'stock-operations' }, { title: 'Purchasing', url: '/purchasing', icon: ClipboardList, permission: 'purchasing' }, { title: 'Receive goods', url: '/purchasing/create', icon: PackagePlus, permission: 'inventory.receive' }, { title: 'Suppliers', url: '/catalog/suppliers', icon: Truck, permission: 'suppliers.manage' }, { title: 'Locations', url: '/catalog/locations', icon: MapPin, permission: 'locations.manage' }] },
    { title: 'Finance & control', items: [{ title: 'Expenses', url: '/expenses', icon: Wallet, permission: 'finance.manage' }, { title: 'Reports', url: '/reports', icon: BarChart3, permission: 'reports.view' }, { title: 'Staff & access', url: '/admin/users', icon: Settings2, permission: 'users.manage' }, { title: 'Audit trail', url: '/admin/audit', icon: History, permission: 'audit.view' }] },
];
export function AppSidebar() {
    const { auth, erp } = usePage<SharedData>().props; const { url } = usePage();
    const can = (permission: string) => !permission || (auth.permissions ?? []).includes(permission);
    const visible = (permission: string) => permission === 'sales' ? can('sales.create') || can('sales.view-all') : permission === 'purchasing' ? can('purchasing.manage') || can('finance.manage') : permission === 'stock-operations' ? can('inventory.adjust') || can('inventory.transfer') : can(permission);
    return <Sidebar collapsible="icon" variant="inset"><SidebarHeader className="px-3 py-4"><SidebarMenu><SidebarMenuItem><SidebarMenuButton size="lg" asChild><Link href="/dashboard"><span className="flex size-9 items-center justify-center rounded-xl bg-emerald-600 text-white"><ShoppingBasket className="size-5" /></span><span className="grid flex-1 text-left"><span className="text-base font-semibold tracking-tight">{erp?.name ?? 'FreshMart'}</span><span className="text-sidebar-foreground/60 text-xs">Retail operations</span></span></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader><SidebarContent>{groups.map(group => {
        const items = group.items.filter(i => visible(i.permission)); if (!items.length) return null;
        return <SidebarGroup key={group.title}><SidebarGroupLabel className="text-[10px] tracking-wider uppercase">{group.title}</SidebarGroupLabel><SidebarGroupContent><SidebarMenu>{items.map(item => <SidebarMenuItem key={item.url}><SidebarMenuButton asChild isActive={url.split('?')[0] === item.url} tooltip={item.title}><Link href={item.url}><item.icon className="size-4" /><span>{item.title}</span></Link></SidebarMenuButton></SidebarMenuItem>)}</SidebarMenu></SidebarGroupContent></SidebarGroup>;
    })}</SidebarContent><SidebarFooter><NavUser /></SidebarFooter></Sidebar>;
}
