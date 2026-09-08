<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [...parent::share($request),
            'name' => config('erp.name'),
            'auth' => ['user' => $user, 'permissions' => $user?->getAllPermissions()->pluck('name')->values() ?? [], 'role' => $user?->getRoleNames()->first()],
            'erp' => ['name' => config('erp.name'), 'address' => config('erp.address'), 'phone' => config('erp.phone'), 'currency' => config('erp.currency'), 'timezone' => config('erp.timezone')],
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
