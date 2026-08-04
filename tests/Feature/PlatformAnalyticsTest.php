<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_analytics_event_is_stored_without_raw_ip_or_user_agent(): void
    {
        $city = City::factory()->create();
        $organization = Organization::factory()->for($city)->create();
        $field = FootballField::factory()->for($organization)->for($city)->create();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.55',
            'HTTP_USER_AGENT' => 'Feature Test Browser',
        ])->postJson('/analytics/events', [
            'event_type' => 'availability_search',
            'visitor_id' => 'visitor-test',
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'city_id' => $city->id,
            'metadata' => ['date' => '2026-07-26', 'customer_name' => ['not allowed']],
        ])->assertOk();

        $event = AnalyticsEvent::query()->firstOrFail();

        $this->assertSame('availability_search', $event->event_type);
        $this->assertSame('visitor-test', $event->visitor_id);
        $this->assertNotSame('203.0.113.55', $event->ip_hash);
        $this->assertNotSame('Feature Test Browser', $event->user_agent_hash);
        $this->assertSame(64, strlen((string) $event->ip_hash));
        $this->assertSame(['date' => '2026-07-26'], $event->metadata);
    }

    public function test_unknown_analytics_event_is_rejected(): void
    {
        try {
            $this->withoutExceptionHandling()
                ->postJson('/analytics/events', ['event_type' => 'customer_phone_submitted']);
            $this->fail('Unknown analytics event was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('event_type', $exception->errors());
        }

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_platform_analytics_is_super_admin_only(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        $this->actingAs($owner)->get('/admin/analytics')->assertForbidden();
        $this->actingAs($employee)->get('/admin/analytics')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/analytics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PlatformAnalytics')
                ->has('analytics.kpis')
                ->has('filters'));
    }

    public function test_platform_analytics_report_counts_public_activity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 12:00:00', 'Europe/Belgrade'));

        $city = City::factory()->create(['name' => 'Ferizaj']);
        $organization = Organization::factory()->for($city)->create(['name' => 'Arena']);
        $field = FootballField::factory()->for($organization)->create(['name' => 'Arena 1']);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'organization_id' => null]);

        AnalyticsEvent::query()->create(['event_type' => 'public_home_view', 'visitor_id' => 'one']);
        AnalyticsEvent::query()->create(['event_type' => 'public_home_view', 'visitor_id' => 'two']);
        AnalyticsEvent::query()->create(['event_type' => 'availability_search', 'visitor_id' => 'one', 'city_id' => $city->id]);
        AnalyticsEvent::query()->create(['event_type' => 'business_view', 'visitor_id' => 'one', 'organization_id' => $organization->id, 'city_id' => $city->id]);
        AnalyticsEvent::query()->create(['event_type' => 'call_click', 'visitor_id' => 'one', 'organization_id' => $organization->id]);
        AnalyticsEvent::query()->create(['event_type' => 'field_view', 'visitor_id' => 'one', 'organization_id' => $organization->id, 'football_field_id' => $field->id]);

        $this->actingAs($superAdmin)->get('/admin/analytics?period=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.kpis.total_visits', 2)
                ->where('analytics.kpis.unique_visitors', 2)
                ->where('analytics.kpis.availability_searches', 1)
                ->where('analytics.kpis.call_clicks', 1)
                ->where('analytics.kpis.business_views', 1)
                ->where('analytics.most_searched_cities.0.city_name', 'Ferizaj')
                ->where('analytics.most_viewed_businesses.0.business_name', 'Arena')
                ->where('analytics.most_clicked_fields.0.field_name', 'Arena 1'));

        CarbonImmutable::setTestNow();
    }
}
