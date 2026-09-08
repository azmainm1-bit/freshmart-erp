<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\LastAdministratorGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastAdministratorGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deactivating_the_only_admin_is_blocked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(
            LastAdministratorGuard::wouldRemoveLastAdmin($admin, willBeActive: false, willHaveAdminRole: true)
        );
    }

    public function test_demoting_the_only_admin_is_blocked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(
            LastAdministratorGuard::wouldRemoveLastAdmin($admin, willBeActive: true, willHaveAdminRole: false)
        );
    }

    public function test_deactivating_an_admin_is_allowed_when_another_active_admin_exists(): void
    {
        $admin1 = User::factory()->create();
        $admin1->assignRole('admin');
        $admin2 = User::factory()->create();
        $admin2->assignRole('admin');

        $this->assertFalse(
            LastAdministratorGuard::wouldRemoveLastAdmin($admin1, willBeActive: false, willHaveAdminRole: true)
        );
    }

    public function test_deactivating_an_admin_is_blocked_if_the_other_admin_is_already_inactive(): void
    {
        $admin1 = User::factory()->create();
        $admin1->assignRole('admin');
        $admin2 = User::factory()->create(['active' => false]);
        $admin2->assignRole('admin');

        $this->assertTrue(
            LastAdministratorGuard::wouldRemoveLastAdmin($admin1, willBeActive: false, willHaveAdminRole: true)
        );
    }

    public function test_a_non_admin_user_is_never_flagged_as_the_last_admin(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->assertFalse(
            LastAdministratorGuard::wouldRemoveLastAdmin($cashier, willBeActive: false, willHaveAdminRole: false)
        );
    }
}
