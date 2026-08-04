<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSessionHasErrors(['email']);

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
