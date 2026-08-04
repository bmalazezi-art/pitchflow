<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedPageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_are_sent_with_no_store_cache_headers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)
            ->get(route('approval.pending'))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $cacheControl = $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function test_protected_pages_redirect_to_login_after_logout(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_auth_status_endpoint_reports_current_session_without_cache(): void
    {
        $this->get(route('auth.status'))
            ->assertUnauthorized()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)->get(route('auth.status'));

        $response->assertNoContent();
        $cacheControl = $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
