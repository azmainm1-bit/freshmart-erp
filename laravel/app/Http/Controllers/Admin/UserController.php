<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\LastAdministratorGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'username', 'name', 'email', 'active']);

        return Inertia::render('admin/users/index', [
            'users' => $users->map(function (User $u): array {
                /** @var string|null $roleName */
                $roleName = $u->roles->pluck('name')->first();

                return [
                    'id' => $u->id,
                    'username' => $u->username,
                    'name' => $u->name,
                    'email' => $u->email,
                    'active' => $u->active,
                    'role' => $roleName,
                ];
            })->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'active' => true,
            ]);
            $user->assignRole($data['role']);

            AuditLog::record($request->user(), 'USER_CREATED', $user, ['role' => $data['role']]);
        });

        return back()->with('success', 'Staff account created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $user, $request) {
            // Serialize access changes even when different administrators are targeted.
            DB::select('select pg_advisory_xact_lock(82471001)');
            $user->refresh()->unsetRelation('roles');
            if (LastAdministratorGuard::wouldRemoveLastAdmin($user, $data['active'], $data['role'] === 'admin')) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator. Create another administrator first.']);
            }
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'active' => $data['active'],
            ]);
            $user->syncRoles([$data['role']]);

            AuditLog::record($request->user(), 'USER_UPDATED', $user, [
                'role' => $data['role'],
                'active' => $data['active'],
            ]);
        });

        return back()->with('success', 'Staff account updated.');
    }
}
