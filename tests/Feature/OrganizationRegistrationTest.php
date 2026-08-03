<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\BusinessApplicationReceived;
use App\Notifications\BusinessApproved;
use App\Notifications\BusinessRejected;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_isolated_pending_organization_and_owner(): void
    {
        Notification::fake();
        $city = City::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Owner Name',
            'email' => 'owner@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'business_name' => 'Prishtina Arena',
            'owner_phone' => '+38344111222',
            'business_phone' => '+38344222333',
            'city_id' => $city->id,
            'business_address' => 'Main Street 1',
            'number_of_fields' => 2,
            'starting_price_per_hour' => 35,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
            'amenities' => ['parking', 'lighting'],
            'preferred_language' => 'en',
        ]);

        $response->assertRedirect(route('approval.pending'));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Prishtina Arena',
            'status' => OrganizationStatus::Pending->value,
            'timezone' => 'Europe/Pristina',
            'currency' => 'EUR',
        ]);
        $organization = Organization::query()->where('name', 'Prishtina Arena')->firstOrFail();
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame($organization->id, $owner->organization_id);
        $this->assertSame(UserRole::Owner, $owner->role);
        Notification::assertSentTo($owner, BusinessApplicationReceived::class);
        Notification::assertSentTo($owner, QueuedVerifyEmail::class);
        $this->assertDatabaseCount('football_fields', 2);
        $this->assertDatabaseCount('operating_hours', 14);
        $this->assertDatabaseHas('football_fields', ['price_per_hour' => 35, 'opening_time' => '12:00']);

        $owner->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('approval.pending'));

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($superAdmin)->get('/admin/organizations?status=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organizations')
                ->where('summary.pending', 1)
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $organization->id)
                ->where('organizations.data.0.status', OrganizationStatus::Pending->value));

        $this->assertFalse(Organization::query()->eligibleForPublicDirectory()->whereKey($organization)->exists());
    }

    public function test_owner_is_notified_when_business_is_approved_or_rejected(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);
        $approvedOrganization = Organization::factory()->create(['status' => OrganizationStatus::Pending, 'approved_at' => null]);
        $approvedOwner = User::factory()->for($approvedOrganization)->create([
            'role' => UserRole::Owner,
            'email' => 'approved-owner@example.com',
            'preferred_language' => 'en',
        ]);
        $rejectedOrganization = Organization::factory()->create(['status' => OrganizationStatus::Pending, 'approved_at' => null]);
        $rejectedOwner = User::factory()->for($rejectedOrganization)->create([
            'role' => UserRole::Owner,
            'email' => 'rejected-owner@example.com',
            'preferred_language' => 'sq',
        ]);

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$approvedOrganization->id}", [
            'status' => 'approved',
        ])->assertRedirect();

        $this->actingAs($superAdmin)->patch("/admin/organizations/{$rejectedOrganization->id}", [
            'status' => 'rejected',
            'rejection_reason' => 'Missing business details.',
        ])->assertRedirect();

        Notification::assertSentTo($approvedOwner, BusinessApproved::class);
        Notification::assertSentTo($rejectedOwner, BusinessRejected::class);
        $this->assertSame(OrganizationStatus::Approved, $approvedOrganization->refresh()->status);
        $this->assertSame(OrganizationStatus::Rejected, $rejectedOrganization->refresh()->status);
    }

    public function test_approved_owner_can_access_workspace_without_email_verification_for_mvp(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Approved]);
        $owner = User::factory()->for($organization)->create([
            'role' => UserRole::Owner,
            'email_verified_at' => null,
        ]);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
    }

    public function test_unverified_user_can_change_email_and_receive_new_verification_link(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['status' => OrganizationStatus::Pending]);
        $owner = User::factory()->for($organization)->create([
            'role' => UserRole::Owner,
            'email' => 'old-owner@example.com',
            'email_verified_at' => null,
        ]);

        $this->actingAs($owner)->patch('/email', ['email' => 'new-owner@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'email' => 'new-owner@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo($owner->refresh(), QueuedVerifyEmail::class);
    }
}
