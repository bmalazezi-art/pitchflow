<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Support\EmployeePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_employee_with_a_queued_secure_invitation(): void
    {
        Notification::fake();
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
        ])->assertRedirect();

        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $this->assertNull($employee->email_verified_at);
        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertSame('invited', $employee->status);
        $this->assertSame(EmployeePermissions::all(), $employee->permissions);
        Notification::assertSentTo($employee, EmployeeInvitation::class);
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
