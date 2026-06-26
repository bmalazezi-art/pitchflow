<?php

namespace Tests\Feature;

use App\Models\FootballField;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAvailabilityPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_does_not_expose_private_customer_fields(): void
    {
        $organization = Organization::factory()->create();
        FootballField::factory()->for($organization)->create(['name' => 'Public Field', 'city_id' => null]);

        $this->get('/?city='.$organization->city_id)->assertOk()
            ->assertSee('Public Field')
            ->assertDontSee('customer_phone')
            ->assertDontSee('payment_status')
            ->assertDontSee('reliability_status');
    }
}
