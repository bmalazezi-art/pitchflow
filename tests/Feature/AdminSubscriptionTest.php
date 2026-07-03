<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_an_organization_subscription(): void
    {
        $platform = Organization::factory()->create();
        $superAdmin = User::factory()->for($platform)->create(['role' => UserRole::SuperAdmin]);
        $organization = Organization::factory()->create(['subscription_plan' => '1–2 Fields']);

        $this->actingAs($superAdmin)->put("/admin/organizations/{$organization->id}/subscription", [
            'plan_name' => '3–5 Fields',
            'price' => 49,
            'billing_cycle' => 'monthly',
            'status' => 'trial',
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $organization->refresh();

        $this->assertSame('3–5 Fields', $organization->subscription_plan);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_name' => '3–5 Fields',
            'price' => 49,
            'billing_cycle' => 'monthly',
            'status' => 'trial',
        ]);
        $this->assertTrue(ActivityLog::query()
            ->where('organization_id', $organization->id)
            ->where('action', 'subscription_updated')
            ->exists());
    }

    public function test_non_super_admin_cannot_update_subscriptions(): void
    {
        $ownerOrganization = Organization::factory()->create();
        $owner = User::factory()->for($ownerOrganization)->create(['role' => UserRole::Owner]);
        $organization = Organization::factory()->create();

        $this->actingAs($owner)->put("/admin/organizations/{$organization->id}/subscription", [
            'plan_name' => '3–5 Fields',
            'price' => 49,
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
            'plan_name' => '3–5 Fields',
        ]);
    }

    public function test_organization_directory_prioritizes_pending_and_supports_admin_filters(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);
        $prishtina = City::factory()->create(['name' => 'Prishtina']);
        $approved = Organization::factory()->for($prishtina)->create(['name' => 'Alpha Arena']);
        FootballField::factory()->for($approved)->create(['city_id' => $prishtina->id]);
        $pending = Organization::factory()->for($prishtina)->create([
            'name' => 'Pending Pitch',
            'email' => 'pending-owner@example.test',
            'status' => OrganizationStatus::Pending,
            'approved_at' => null,
        ]);

        $this->actingAs($superAdmin)->get('/admin/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organizations')
                ->where('organizations.data.0.id', $pending->id)
                ->where('summary.pending', 1));

        $this->actingAs($superAdmin)->get('/admin/organizations?search=pending-owner&status=pending&city='.$prishtina->id.'&visibility=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $pending->id)
                ->where('filters.search', 'pending-owner'));
    }
}
