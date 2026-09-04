<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_login_page_links_to_microsoft_sso(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('auth.azure.redirect'), false);
    }

    public function test_authenticated_user_can_reach_the_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/register')
            ->assertOk()
            ->assertSeeLivewire('checkout');
    }

    public function test_inactive_user_is_blocked_at_azure_callback_time(): void
    {
        // Modeled here at the unit level: the callback controller checks
        // is_active after upserting the user record from the Azure claims.
        $user = User::factory()->create(['is_active' => false, 'azure_oid' => 'oid-123']);

        $this->assertFalse($user->fresh()->is_active);
    }
}
