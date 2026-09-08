<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEV FIXTURE DATA — not for production use (docs/ARCHITECTURE.md). Mirrors
 * the legacy server/prisma/seed.ts accounts so local development/testing
 * has the same known logins. Passwords are intentionally simple/well-known
 * for local development only. Never run this seeder against a database
 * that could become production — use `php artisan app:create-admin`
 * instead for a real deployment's first account.
 */
class DevUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Development accounts may only be seeded in local/testing environments.');
        }
        $accounts = [
            ['username' => 'admin', 'name' => 'Dev Admin', 'role' => 'admin', 'password' => 'admin123'],
            ['username' => 'manager1', 'name' => 'Dev Manager', 'role' => 'manager', 'password' => 'manager123'],
            ['username' => 'cashier1', 'name' => 'Dev Cashier', 'role' => 'cashier', 'password' => 'cashier123'],
            ['username' => 'accountant1', 'name' => 'Dev Accountant', 'role' => 'accountant', 'password' => 'accountant123'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'email' => $account['username'].'@dev.local',
                    'password' => $account['password'],
                    'active' => true,
                ]
            );
            $user->syncRoles([$account['role']]);
        }

        $this->command?->info('Dev accounts (username / password):');
        foreach ($accounts as $account) {
            $this->command?->line("  {$account['username']} / {$account['password']}  ({$account['role']})");
        }
    }
}
