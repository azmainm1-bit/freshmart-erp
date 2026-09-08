<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function cashier(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        return $user;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    /**
     * Direct HTTP test, independent of any frontend control being
     * hidden/disabled — docs/ARCHITECTURE.md requirement #11.
     */
    public function test_a_cashier_cannot_view_the_staff_list_even_by_hitting_the_url_directly(): void
    {
        $this->actingAs($this->cashier())
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_a_cashier_cannot_create_a_staff_account_via_direct_http_post(): void
    {
        $this->actingAs($this->cashier())
            ->post('/admin/users', [
                'username' => 'newcashier',
                'name' => 'New Cashier',
                'password' => 'a-strong-password-123',
                'role' => 'cashier',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'newcashier']);
    }

    public function test_an_admin_can_view_the_staff_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_an_admin_can_create_a_staff_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'username' => 'cashier2',
                'name' => 'Second Cashier',
                'password' => 'a-strong-password-123',
                'role' => 'cashier',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $created = User::where('username', 'cashier2')->firstOrFail();
        $this->assertTrue($created->hasRole('cashier'));
        $this->assertTrue($created->active);

        $this->assertDatabaseHas('audit_log_entries', [
            'actor_id' => $admin->id,
            'action' => 'USER_CREATED',
            'auditable_id' => $created->id,
        ]);
    }

    public function test_creating_a_user_with_a_duplicate_username_is_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'username' => 'taken',
                'name' => 'Someone',
                'password' => 'a-strong-password-123',
                'role' => 'cashier',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_deactivating_the_last_admin_is_rejected_at_the_http_layer(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => null,
                'role' => 'admin',
                'active' => false,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->active);
    }

    public function test_deactivating_a_non_last_admin_succeeds_and_is_audited(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();

        $this->actingAs($admin1)
            ->put("/admin/users/{$admin2->id}", [
                'name' => $admin2->name,
                'email' => null,
                'role' => 'admin',
                'active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin2->fresh()->active);
        $this->assertDatabaseHas('audit_log_entries', [
            'actor_id' => $admin1->id,
            'action' => 'USER_UPDATED',
            'auditable_id' => $admin2->id,
        ]);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();

        $this->actingAs($admin1)->put("/admin/users/{$admin2->id}", [
            'name' => $admin2->name,
            'email' => null,
            'role' => 'admin',
            'active' => false,
        ]);

        $this->post('/logout');

        $this->post('/login', ['login' => $admin2->username, 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }
}
