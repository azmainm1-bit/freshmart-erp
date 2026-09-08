<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Controlled initial-admin creation (docs/ARCHITECTURE.md) — no public
 * registration route can create an admin account, so this is the only way
 * to bootstrap a real deployment's first account. Run once at cutover
 * time, interactively, never as part of an automated/unattended seed.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--username= : Login username}
        {--name= : Full name}
        {--password= : Password (omit to auto-generate and print once)}';

    protected $description = 'Create the first administrator account for a fresh deployment';

    public function handle(): int
    {
        if (User::role('admin')->exists()) {
            $this->error('An admin account already exists. This command only bootstraps the first one.');
            $this->line('Use the staff management screens (or the tinker console) to add further admins.');

            return self::FAILURE;
        }

        $username = $this->option('username') ?: $this->ask('Username');
        $name = $this->option('name') ?: $this->ask('Full name');

        $generated = false;
        $password = $this->option('password');
        if (! $password) {
            $password = Str::password(16);
            $generated = true;
        }

        $validator = validator(
            ['username' => $username, 'name' => $name, 'password' => $password],
            ['username' => ['required', 'string', 'unique:users,username'], 'name' => ['required', 'string'], 'password' => ['required', Password::min(12)]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'username' => $username,
            'name' => $name,
            'email' => null,
            'password' => $password,
            'active' => true,
        ]);
        $user->assignRole('admin');

        $this->info("Admin account created: {$username}");
        if ($generated) {
            $this->warn('Generated password (shown once — save it now):');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
