<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = ['users.manage', 'roles.manage', 'audit.view', 'products.manage', 'products.view-cost', 'suppliers.manage', 'locations.manage', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer', 'purchasing.manage', 'finance.manage', 'reports.view', 'sales.create', 'sales.view-all', 'sales.discount', 'sales.return', 'customers.manage'];
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $roles = [
            'admin' => ['users.manage', 'roles.manage', 'audit.view', 'products.manage', 'products.view-cost', 'suppliers.manage', 'locations.manage', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer', 'purchasing.manage', 'finance.manage', 'reports.view', 'sales.create', 'sales.view-all', 'sales.discount', 'sales.return', 'customers.manage'],
            'manager' => ['audit.view', 'products.manage', 'products.view-cost', 'suppliers.manage', 'locations.manage', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer', 'purchasing.manage', 'finance.manage', 'reports.view', 'sales.create', 'sales.view-all', 'sales.discount', 'sales.return', 'customers.manage'],
            'cashier' => ['inventory.view', 'sales.create', 'customers.manage'],
            'inventory' => ['products.manage', 'products.view-cost', 'suppliers.manage', 'inventory.view', 'inventory.receive', 'inventory.transfer', 'purchasing.manage'],
            'accountant' => ['inventory.view', 'products.view-cost', 'audit.view', 'finance.manage', 'reports.view', 'sales.view-all', 'customers.manage'],
        ];
        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
