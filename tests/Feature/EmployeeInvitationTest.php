<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\EmployeePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_employee_with_secure_local_invitation_link(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();

        $this->actingAs($owner)->post('/employees', [
            'first_name' => 'New',
            'last_name' => 'Employee',
            'email' => 'employee@example.com',
            'phone' => '+38344123456',
            'preferred_language' => 'sq',
            'field_ids' => [$field->id],
            'permissions' => EmployeePermissions::all(),
        ])->assertRedirect()
            ->assertSessionHas('invite_url')
            ->assertSessionHas('invite_link')
            ->assertSessionMissing('invite_notice')
            ->assertSessionHas('success', __('messages.employee_invitation_created_success'));

        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $this->assertSame($organization->id, $employee->organization_id);
        $this->assertNull($employee->email_verified_at);
        $this->assertNull($employee->password);
        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertSame('invited', $employee->status);
        $this->assertSame(EmployeePermissions::all(), $employee->permissions);
        $this->assertNotNull($employee->invitation_token_hash);
        $this->assertTrue($employee->invitation_expires_at->greaterThan(now()->addDays(6)));
        $this->assertTrue($employee->assignedFields()->whereKey($field)->exists());
        $this->assertSame(session('invite_url'), session('invite_link'));
        $this->assertStringContainsString('/employee/invite/', session('invite_link'));
    }

    public function test_employee_accepts_invitation_by_creating_password_only(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();

        $this->actingAs($owner)->post('/employees', [
            'first_name' => 'Invited',
            'last_name' => 'Employee',
            'email' => 'invited@example.com',
            'phone' => '+38344123456',
            'preferred_language' => 'en',
            'field_ids' => [$field->id],
            'permissions' => [EmployeePermissions::CREATE_RESERVATIONS, EmployeePermissions::VIEW_CALENDAR],
        ]);
        $inviteLink = session('invite_link');
        auth()->logout();

        $this->get(parse_url($inviteLink, PHP_URL_PATH))->assertOk();
        $this->post(parse_url($inviteLink, PHP_URL_PATH), [
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('login'));

        $employee = User::query()->where('email', 'invited@example.com')->firstOrFail();
        $this->assertSame('active', $employee->status);
        $this->assertNotNull($employee->password);
        $this->assertNotNull($employee->invitation_accepted_at);
        $this->assertNull($employee->invitation_token_hash);
        $this->assertTrue(Hash::check('StrongPassword123!', $employee->password));

        $this->post('/login', ['email' => $employee->email, 'password' => 'StrongPassword123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_owner_can_invite_employee_with_phone_only_and_employee_logs_in_with_phone(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();

        $this->actingAs($owner)->post('/employees', [
            'first_name' => 'Phone',
            'last_name' => 'Employee',
            'email' => '',
            'phone' => '+383 44 555 777',
            'preferred_language' => 'en',
            'field_ids' => [$field->id],
            'permissions' => [EmployeePermissions::VIEW_CALENDAR],
        ])->assertRedirect()
            ->assertSessionHas('invite_url')
            ->assertSessionHas('invite_link');

        $inviteLink = session('invite_link');
        $employee = User::query()->where('phone_normalized', '+38344555777')->firstOrFail();
        $this->assertNull($employee->email);
        $this->assertSame($organization->id, $employee->organization_id);

        auth()->logout();
        $this->post(parse_url($inviteLink, PHP_URL_PATH), [
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => '+383 44 555 777', 'password' => 'StrongPassword123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_employee_phone_must_be_unique_inside_owner_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();
        User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'phone' => '+383 44 555 777',
            'phone_normalized' => '+38344555777',
        ]);

        $this->actingAs($owner)->post('/employees', [
            'first_name' => 'Duplicate',
            'last_name' => 'Employee',
            'email' => '',
            'phone' => '00383 44 555 777',
            'preferred_language' => 'en',
            'field_ids' => [$field->id],
            'permissions' => [EmployeePermissions::VIEW_CALENDAR],
        ])->assertSessionHasErrors('phone');
    }

    public function test_soft_deleted_employee_identity_can_be_reused_for_new_invitation(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();
        User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'phone' => '044 717 214',
            'phone_normalized' => '044717214',
        ])->delete();

        $this->actingAs($owner)->post('/employees', [
            'first_name' => 'Bedridin',
            'last_name' => 'Xhinali',
            'email' => null,
            'phone' => '044 717 214',
            'preferred_language' => 'en',
            'field_ids' => [$field->id],
            'permissions' => EmployeePermissions::all(),
        ])->assertRedirect()
            ->assertSessionHas('invite_url')
            ->assertSessionMissing('errors');

        $this->assertDatabaseHas('users', [
            'organization_id' => $organization->id,
            'name' => 'Bedridin Xhinali',
            'phone_normalized' => '044717214',
            'status' => 'invited',
        ]);
    }

    public function test_owner_can_resend_pending_employee_invitation(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'status' => 'invited',
            'password' => null,
            'invitation_token_hash' => hash('sha256', 'old-token'),
            'invitation_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)->post("/employees/{$employee->id}/resend-invitation")
            ->assertRedirect()
            ->assertSessionHas('invite_link');

        $employee->refresh();
        $this->assertNotSame(hash('sha256', 'old-token'), $employee->invitation_token_hash);
        $this->assertTrue($employee->invitation_expires_at->greaterThan(now()->addDays(6)));
    }

    public function test_owner_can_create_password_reset_link_for_active_phone_only_employee(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'email' => null,
            'phone' => '+383 44 777 888',
            'phone_normalized' => '+38344777888',
            'status' => 'active',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($owner)->post("/employees/{$employee->id}/reset-password-link")
            ->assertRedirect()
            ->assertSessionHas('reset_url')
            ->assertSessionHas('reset_link')
            ->assertSessionMissing('reset_notice')
            ->assertSessionHas('success', __('messages.employee_password_reset_link_created'));

        $resetLink = session('reset_link');
        $employee->refresh();
        $this->assertNotNull($employee->invitation_token_hash);
        $this->assertTrue($employee->invitation_expires_at->greaterThan(now()->addDays(6)));

        auth()->logout();
        $this->post(parse_url($resetLink, PHP_URL_PATH), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));

        $employee->refresh();
        $this->assertSame('active', $employee->status);
        $this->assertTrue(Hash::check('NewPassword123!', $employee->password));
        $this->assertNull($employee->invitation_token_hash);

        $this->post('/login', ['email' => '+383 44 777 888', 'password' => 'NewPassword123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_employee_reset_link_is_shared_with_employees_page(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post("/employees/{$employee->id}/reset-password-link")
            ->assertRedirect();

        $resetLink = session('reset_link');

        $this->actingAs($owner)->withSession([
            'success' => __('messages.employee_password_reset_link_created'),
            'reset_url' => $resetLink,
            'reset_link' => $resetLink,
        ])->get('/employees')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Index')
                ->where('flash.reset_url', $resetLink)
                ->where('flash.reset_link', $resetLink)
                ->where('flash.reset_notice', null));
    }

    public function test_logged_in_owner_opening_employee_invite_sees_password_form(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $token = 'fresh-token';
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'status' => 'invited',
            'password' => null,
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)->get("/employee/invite/{$token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/EmployeeInvite')
                ->where('employee.name', $employee->name));

        $this->assertGuest();
    }

    public function test_owner_cannot_create_password_reset_link_for_disabled_employee(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'status' => 'disabled',
        ]);

        $this->actingAs($owner)->post("/employees/{$employee->id}/reset-password-link")
            ->assertStatus(422);
    }

    public function test_expired_employee_invitation_cannot_be_accepted(): void
    {
        $organization = Organization::factory()->create();
        $token = 'expired-token';
        User::factory()->for($organization)->create([
            'role' => UserRole::Employee,
            'status' => 'invited',
            'password' => null,
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => now()->subMinute(),
        ]);

        $this->get("/employee/invite/{$token}")
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('messages.employee_invitation_invalid'));
    }

    public function test_disabled_employee_cannot_log_in(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create([
            'status' => 'disabled',
            'password' => Hash::make('LocalPassword123!'),
        ]);

        $this->post('/login', ['email' => $employee->email, 'password' => 'LocalPassword123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_employee_without_create_permission_cannot_create_reservations(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['permissions' => []]);

        $this->assertFalse(Gate::forUser($employee)->allows('create', Reservation::class));
    }
}
