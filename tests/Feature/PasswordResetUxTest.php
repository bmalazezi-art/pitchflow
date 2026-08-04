<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_accepts_email_and_shows_local_reset_link(): void
    {
        config(['app.env' => 'local']);
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success', __('messages.reset_link_sent'))
            ->assertSessionHas('reset_notice', __('messages.employee_invitation_email_disabled_local'))
            ->assertSessionHas('reset_url');

        $this->assertStringContainsString('/reset-password/', session('reset_url'));
        $this->assertStringContainsString('email=owner%40example.com', session('reset_url'));
    }

    public function test_forgot_password_accepts_employee_phone_when_email_exists(): void
    {
        config(['app.env' => 'local']);
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'email' => 'employee@example.com',
            'phone' => '+383 44 123 456',
            'phone_normalized' => '+38344123456',
        ]);

        $this->post('/forgot-password', ['email' => '+383 44 123 456'])
            ->assertRedirect()
            ->assertSessionHas('success', __('messages.reset_link_sent'))
            ->assertSessionHas('reset_url');

        $this->assertStringContainsString('email=employee%40example.com', session('reset_url'));
        $this->assertSame('employee@example.com', $employee->refresh()->email);
    }

    public function test_forgot_password_does_not_expose_reset_link_in_production_when_mail_is_disabled(): void
    {
        config(['app.env' => 'production', 'mail.default' => 'log']);
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success', __('messages.password_reset_temporarily_unavailable'))
            ->assertSessionMissing('reset_notice')
            ->assertSessionMissing('reset_url');
    }

    public function test_forgot_password_phone_only_employee_gets_owner_help_message(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'email' => null,
            'phone' => '+383 44 654 321',
            'phone_normalized' => '+38344654321',
        ]);

        $this->post('/forgot-password', ['email' => '+383 44 654 321'])
            ->assertRedirect()
            ->assertSessionHas('success', __('messages.employee_password_reset_owner_help'))
            ->assertSessionMissing('reset_url');
    }
}
