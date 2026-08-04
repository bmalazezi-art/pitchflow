<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_email_and_empty_password_cannot_login(): void
    {
        $this->createSuperAdmin();

        $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_correct_email_with_empty_password_cannot_login(): void
    {
        $user = $this->createSuperAdmin();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => '',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['password']);

        $this->assertGuest();
    }

    public function test_empty_email_with_any_password_cannot_login(): void
    {
        $this->createSuperAdmin();

        $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'CorrectPassword123!',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_correct_email_with_wrong_password_cannot_login(): void
    {
        $user = $this->createSuperAdmin();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.password')]);

        $this->assertGuest();
    }

    public function test_wrong_email_with_valid_password_gets_no_account_message(): void
    {
        $this->createSuperAdmin();

        $this->from('/login')->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'CorrectPassword123!',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.no_account')]);

        $this->assertGuest();
    }

    public function test_correct_email_and_correct_password_logs_in(): void
    {
        $user = $this->createSuperAdmin();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('admin.organizations'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_random_login_attempt_while_already_authenticated_logs_out_and_fails(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->from('/login')
            ->post('/login', [
                'email' => 'random-person@example.test',
                'password' => 'definitely-wrong',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.no_account')]);

        $this->assertGuest();
    }

    public function test_empty_login_attempt_while_already_authenticated_logs_out_and_fails(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->from('/login')
            ->post('/login', [
                'email' => '',
                'password' => '',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_no_demo_quick_or_impersonation_login_routes_exist(): void
    {
        $dangerousRouteNames = collect(Route::getRoutes())->map(fn ($route) => implode(' ', [
            $route->uri(),
            $route->getName() ?? '',
            $route->getActionName(),
        ]))->filter(fn (string $route) => preg_match('/(demo|quick|impersonat).*login|login.*(demo|quick|impersonat)/i', $route));

        $this->assertCount(0, $dangerousRouteNames, $dangerousRouteNames->implode("\n"));
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => UserRole::SuperAdmin,
            'status' => 'active',
        ]);
    }
}
