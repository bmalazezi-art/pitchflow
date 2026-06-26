<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'name' => 'New Employee',
            'email' => 'employee@example.com',
            'phone' => '+38344123456',
            'preferred_language' => 'sq',
            'field_ids' => [$field->id],
        ])->assertRedirect();

        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $this->assertNull($employee->email_verified_at);
        $this->assertSame(UserRole::Employee, $employee->role);
        Notification::assertSentTo($employee, EmployeeInvitation::class);
    }
}
