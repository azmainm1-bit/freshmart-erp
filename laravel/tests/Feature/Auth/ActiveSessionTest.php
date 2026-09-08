<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_account_loses_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        User::whereKey($user->id)->update(['active' => false]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_disabled_account_cannot_use_json_endpoints(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->actingAs($user)->postJson('/api/goods-receipts', [])->assertUnauthorized();
    }
}
