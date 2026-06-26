<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
