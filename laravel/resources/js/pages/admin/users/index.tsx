import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Staff', href: '/admin/users' }];

const ROLES = ['admin', 'manager', 'cashier', 'accountant'] as const;

interface StaffUser {
    id: string;
    username: string;
    name: string;
    email: string | null;
    active: boolean;
    role: string | null;
}

export default function UsersIndex({ users }: { users: StaffUser[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Staff" />
            <div className="flex flex-col gap-6 p-4">
                <CreateUserForm />

                <section aria-labelledby="staff-list-heading" className="rounded-xl border">
                    <h2 id="staff-list-heading" className="border-b p-4 text-lg font-semibold">
                        Staff accounts
                    </h2>
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground border-b text-left">
                            <tr>
                                <th className="p-3 font-medium">Username</th>
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Email</th>
                                <th className="p-3 font-medium">Role</th>
                                <th className="p-3 font-medium">Active</th>
                                <th className="p-3 font-medium">
                                    <span className="sr-only">Save</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <UserRow key={user.id} user={user} />
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </AppLayout>
    );
}

function CreateUserForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        name: '',
        email: '',
        password: '',
        role: 'cashier',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/admin/users', { onSuccess: () => reset('username', 'name', 'email', 'password') });
    };

    return (
        <form onSubmit={submit} className="rounded-xl border p-4" aria-labelledby="create-staff-heading">
            <h2 id="create-staff-heading" className="mb-4 text-lg font-semibold">
                Add staff account
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div className="grid gap-1.5">
                    <Label htmlFor="username">Username</Label>
                    <Input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} required />
                    <InputError message={errors.username} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="name">Full name</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="email">Email (optional)</Label>
                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    <InputError message={errors.email} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                        minLength={12}
                    />
                    <InputError message={errors.password} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="role">Role</Label>
                    <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                        <SelectTrigger id="role">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {ROLES.map((role) => (
                                <SelectItem key={role} value={role}>
                                    {role}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <Button type="submit" className="mt-4" disabled={processing}>
                Create account
            </Button>
        </form>
    );
}

function UserRow({ user }: { user: StaffUser }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: user.name,
        email: user.email ?? '',
        role: user.role ?? 'cashier',
        active: user.active,
    });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    return (
        <tr className="border-b last:border-0">
            <td className="p-3 font-mono">{user.username}</td>
            <td className="p-3">
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} aria-label={`Name for ${user.username}`} />
                <InputError message={errors.name} />
            </td>
            <td className="p-3">
                <Input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    aria-label={`Email for ${user.username}`}
                />
                <InputError message={errors.email} />
            </td>
            <td className="p-3">
                <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                    <SelectTrigger aria-label={`Role for ${user.username}`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ROLES.map((role) => (
                            <SelectItem key={role} value={role}>
                                {role}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.role} />
            </td>
            <td className="p-3">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={data.active}
                        onChange={(e) => setData('active', e.target.checked)}
                        aria-label={`Active status for ${user.username}`}
                    />
                    {data.active ? 'Active' : 'Deactivated'}
                </label>
            </td>
            <td className="p-3">
                <Button type="button" size="sm" onClick={save} disabled={processing || !isDirty}>
                    Save
                </Button>
            </td>
        </tr>
    );
}
