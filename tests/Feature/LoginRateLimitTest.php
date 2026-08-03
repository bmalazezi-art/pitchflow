<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_lockout_returns_form_error_instead_of_full_429_page(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->for($organization)->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => UserRole::Owner,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('messages.too_many_login_attempts')]);

        $this->get('/login')->assertOk();
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => UserRole::Owner,
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect()
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }
}
