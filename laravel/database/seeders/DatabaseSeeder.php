<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Default seeding creates demo data and is restricted to local/testing. Use RolesAndPermissionsSeeder and app:create-admin in production.');
        }
        $this->call(DemoDataSeeder::class);
    }
}
