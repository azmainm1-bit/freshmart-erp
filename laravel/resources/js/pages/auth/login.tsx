import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    login: string;
    password: string;
    remember: boolean;
    [key: string]: string | boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        login: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Staff sign in" description="Sign in to your store workspace.">
            <Head title="Staff sign in" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="login">Username</Label>
                        <Input
                            id="login"
                            type="text"
                            required
                            autoFocus
                            autoComplete="username"
                            value={data.login}
                            onChange={(e) => setData('login', e.target.value)}
                            placeholder="cashier1"
                        />
                        <InputError message={errors.login} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm">
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <div className="relative">
                            <Input
                                className="pr-12"
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                required
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Password"
                            />
                            <button
                                type="button"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                aria-pressed={showPassword}
                                onClick={() => setShowPassword(!showPassword)}
                                className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md focus-visible:outline-2 focus-visible:outline-offset-2"
                            >
                                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                        />
                        <Label htmlFor="remember">Remember me on this device</Label>
                    </div>

                    <Button type="submit" className="mt-4 w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </div>

                <p className="text-muted-foreground text-center text-xs">Need access? Ask your store administrator.</p>
            </form>

            {status && (
                <div role="status" className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
