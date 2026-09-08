<?php

namespace Tests\Feature\Catalog;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAndLocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function manager(): User
    {
        $u = User::factory()->create();
        $u->assignRole('manager');

        return $u;
    }

    private function cashier(): User
    {
        $u = User::factory()->create();
        $u->assignRole('cashier');

        return $u;
    }

    public function test_a_manager_can_create_a_supplier(): void
    {
        $this->actingAs($this->manager())
            ->post('/catalog/suppliers', ['name' => 'ACME Distributors', 'phone' => '01700000000'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['name' => 'ACME Distributors']);
    }

    public function test_a_cashier_cannot_create_a_supplier(): void
    {
        $this->actingAs($this->cashier())
            ->post('/catalog/suppliers', ['name' => 'Nope Inc'])
            ->assertForbidden();

        $this->assertDatabaseMissing('suppliers', ['name' => 'Nope Inc']);
    }

    public function test_a_manager_can_update_and_deactivate_a_supplier(): void
    {
        $supplier = Supplier::create(['name' => 'ACME Distributors', 'active' => true]);

        $this->actingAs($this->manager())
            ->put("/catalog/suppliers/{$supplier->id}", ['name' => 'ACME Distributors Ltd', 'phone' => '01700000000', 'active' => false])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'ACME Distributors Ltd', 'active' => false]);
    }

    public function test_a_cashier_cannot_update_a_supplier(): void
    {
        $supplier = Supplier::create(['name' => 'ACME Distributors', 'active' => true]);

        $this->actingAs($this->cashier())
            ->put("/catalog/suppliers/{$supplier->id}", ['name' => 'Renamed', 'active' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'ACME Distributors']);
    }

    public function test_a_manager_can_create_a_location_with_a_valid_type(): void
    {
        $this->actingAs($this->manager())
            ->post('/catalog/locations', ['name' => 'Main Stockroom', 'type' => 'stockroom'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['name' => 'Main Stockroom', 'type' => 'stockroom']);
    }

    public function test_an_invalid_location_type_is_rejected(): void
    {
        $this->actingAs($this->manager())
            ->post('/catalog/locations', ['name' => 'Weird Place', 'type' => 'not_a_real_type'])
            ->assertSessionHasErrors('type');
    }
}
