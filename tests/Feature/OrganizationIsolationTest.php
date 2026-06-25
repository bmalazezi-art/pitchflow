<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_view_a_customer_from_another_organization(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $owner = User::factory()->for($first)->create(['role' => UserRole::Owner]);
        $customer = Customer::factory()->for($second)->create();

        $this->actingAs($owner)->get("/customers/{$customer->id}")->assertForbidden();
    }

    public function test_customer_index_only_contains_the_current_organization(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $owner = User::factory()->for($first)->create(['role' => UserRole::Owner]);
        Customer::factory()->for($first)->create(['name' => 'Visible Customer']);
        Customer::factory()->for($second)->create(['name' => 'Private Customer']);

        $this->actingAs($owner)->get('/customers')
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee('Private Customer');
    }
}
