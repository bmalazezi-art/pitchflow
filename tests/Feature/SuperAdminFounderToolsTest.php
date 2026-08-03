<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\OrganizationAdminNote;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\BusinessReactivated;
use App\Notifications\BusinessRejected;
use App\Notifications\BusinessSuspended;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminFounderToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_organization_and_owner_setup_link(): void
    {
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $response = $this->actingAs($superAdmin)->post('/admin/organizations', [
            'business_name' => 'Arena Ferizaj',
            'owner_name' => 'Baki Owner',
            'owner_phone' => '044 987 654',
            'owner_email' => null,
            'city_id' => $city->id,
            'address' => 'Main road',
            'public_phone' => '049 111 222',
            'number_of_fields' => 2,
            'starting_price_per_hour' => 30,
            'status' => 'approved',
        ]);

        $response->assertRedirect()->assertSessionHas('invite_url');

        $organization = Organization::query()->where('name', 'Arena Ferizaj')->firstOrFail();
        $this->assertSame(OrganizationStatus::Approved, $organization->status);
        $this->assertDatabaseHas('users', [
            'organization_id' => $organization->id,
            'name' => 'Baki Owner',
            'role' => UserRole::Owner->value,
            'status' => 'invited',
            'phone_normalized' => '044987654',
        ]);
        $this->assertSame(2, FootballField::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'organization_created', 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'owner_invited', 'organization_id' => $organization->id]);
    }

    public function test_organization_page_shows_visibility_and_health_metadata(): void
    {
        $city = City::factory()->create();
        $organization = Organization::factory()->for($city)->create(['name' => 'Needs Setup', 'phone' => '']);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($superAdmin)->get('/admin/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organizations')
                ->has('organizations.data.0.visibility_checklist.items')
                ->where('organizations.data.0.visibility_checklist.is_public', false)
                ->where('organizations.data.0.health_status', 'inactive'));
    }

    public function test_super_admin_status_changes_require_reasons_for_reject_and_suspend_and_create_history(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Approved]);
        $owner = User::factory()->for($organization)->create([
            'role' => UserRole::Owner,
            'email' => 'owner-status@example.com',
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$organization->id}", [
            'status' => 'suspended',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$organization->id}", [
            'status' => 'suspended',
            'reason' => 'Needs verification.',
        ])->assertRedirect()->assertSessionHas('status_undo');

        $this->assertSame(OrganizationStatus::Suspended, $organization->refresh()->status);
        $this->assertDatabaseHas('organization_status_histories', [
            'organization_id' => $organization->id,
            'user_id' => $superAdmin->id,
            'previous_status' => 'approved',
            'new_status' => 'suspended',
            'reason' => 'Needs verification.',
        ]);
        Notification::assertSentTo($owner, BusinessSuspended::class);
    }

    public function test_super_admin_can_restore_rejected_or_suspended_organization_status(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Rejected, 'rejected_at' => now()]);
        $owner = User::factory()->for($organization)->create([
            'role' => UserRole::Owner,
            'email' => 'restore-owner@example.com',
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$organization->id}", [
            'status' => 'pending',
        ])->assertRedirect();

        $this->assertSame(OrganizationStatus::Pending, $organization->refresh()->status);
        $this->assertNull($organization->rejected_at);

        $organization->update(['status' => OrganizationStatus::Suspended, 'suspended_at' => now()]);
        $this->actingAs($superAdmin)->patch("/admin/organizations/{$organization->id}", [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame(OrganizationStatus::Approved, $organization->refresh()->status);
        $this->assertNull($organization->suspended_at);
        Notification::assertSentTo($owner, BusinessReactivated::class);
    }

    public function test_rejecting_organization_with_reason_sends_owner_email_and_hides_it_publicly(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Pending]);
        $owner = User::factory()->for($organization)->create([
            'role' => UserRole::Owner,
            'email' => 'reject-owner@example.com',
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$organization->id}", [
            'status' => 'rejected',
            'reason' => 'Duplicate or suspicious listing.',
        ])->assertRedirect()->assertSessionHas('status_undo');

        $this->assertSame(OrganizationStatus::Rejected, $organization->refresh()->status);
        $this->assertFalse(Organization::query()->eligibleForPublicDirectory()->whereKey($organization)->exists());
        $this->assertDatabaseHas('organization_status_histories', [
            'organization_id' => $organization->id,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'reason' => 'Duplicate or suspicious listing.',
        ]);
        Notification::assertSentTo($owner, BusinessRejected::class);
    }

    public function test_super_admin_notes_are_private_to_super_admins(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($owner)->post("/admin/organizations/{$organization->id}/notes", [
            'note' => 'Owner should not write this.',
        ])->assertForbidden();

        $this->actingAs($superAdmin)->post("/admin/organizations/{$organization->id}/notes", [
            'note' => 'Owner contacted via Instagram.',
        ])->assertRedirect();

        $this->assertDatabaseHas('organization_admin_notes', [
            'organization_id' => $organization->id,
            'user_id' => $superAdmin->id,
            'note' => 'Owner contacted via Instagram.',
        ]);
        $this->assertSame(1, OrganizationAdminNote::query()->count());
    }

    public function test_owner_can_submit_support_request_and_super_admin_can_update_it(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($owner)->post('/support-requests', [
            'message' => 'Need help setting working hours.',
        ])->assertRedirect();

        $request = SupportRequest::query()->firstOrFail();
        $this->assertSame('open', $request->status);

        $this->actingAs($superAdmin)->get('/admin/support-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SupportRequests')
                ->where('requests.data.0.message', 'Need help setting working hours.'));

        $this->actingAs($superAdmin)->patch("/admin/support-requests/{$request->id}", [
            'status' => 'solved',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_requests', ['id' => $request->id, 'status' => 'solved']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'support_request_updated']);
    }

    public function test_audit_logs_are_super_admin_only(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);
        ActivityLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'action' => 'settings_changed',
            'description' => 'Settings changed',
        ]);

        $this->actingAs($owner)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLogs')
                ->where('logs.data.0.organization.name', $organization->name)
                ->where('logs.data.0.action', 'settings_changed'));
    }
}
