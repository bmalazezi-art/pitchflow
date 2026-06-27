<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_dashboard_without_lazy_loading_football_field_organization(): void
    {
        Model::preventLazyLoading();

        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        FootballField::factory()->for($organization)->create();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
