<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_organization_cannot_access_the_application(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Suspended]);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('approval.pending'));
    }
}
