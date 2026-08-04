<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformAnalyticsService
{
    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = AnalyticsEvent::query()->whereBetween('created_at', [$from, $to]);
        $totalVisits = (clone $base)->where('event_type', 'public_home_view')->count();
        $businessViews = (clone $base)->where('event_type', 'business_view')->count();
        $availabilitySearches = (clone $base)->where('event_type', 'availability_search')->count();
        $callClicks = (clone $base)->where('event_type', 'call_click')->count();

        return [
            'kpis' => [
                'total_visits' => $totalVisits,
                'unique_visitors' => (clone $base)->distinct('visitor_id')->whereNotNull('visitor_id')->count('visitor_id'),
                'availability_searches' => $availabilitySearches,
                'call_clicks' => $callClicks,
                'register_business_clicks' => (clone $base)->where('event_type', 'register_business_click')->count(),
                'business_views' => $businessViews,
            ],
            'conversions' => [
                'search_conversion' => $this->percentage($availabilitySearches, $totalVisits),
                'call_conversion' => $this->percentage($callClicks, $businessViews),
                'registration_interest' => $this->percentage((clone $base)->where('event_type', 'register_business_click')->count(), $totalVisits),
            ],
            'visits_over_time' => $this->series($from, $to, 'public_home_view'),
            'searches_over_time' => $this->series($from, $to, 'availability_search'),
            'call_clicks_over_time' => $this->series($from, $to, 'call_click'),
            'most_searched_cities' => $this->mostSearchedCities($from, $to),
            'most_viewed_businesses' => $this->mostViewedBusinesses($from, $to),
            'most_clicked_fields' => $this->mostClickedFields($from, $to),
        ];
    }

    private function series(CarbonImmutable $from, CarbonImmutable $to, string $eventType): array
    {
        $counts = AnalyticsEvent::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as aggregate')
            ->where('event_type', $eventType)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $days = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }

    private function mostSearchedCities(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('analytics_events')
            ->join('cities', 'cities.id', '=', 'analytics_events.city_id')
            ->select('analytics_events.city_id', 'cities.name as city_name', DB::raw('COUNT(*) as search_count'))
            ->where('analytics_events.event_type', 'availability_search')
            ->whereNotNull('analytics_events.city_id')
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->groupBy('analytics_events.city_id', 'cities.name')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get()
            ->map(fn (object $event) => [
                'city_id' => (int) $event->city_id,
                'city_name' => (string) $event->city_name,
                'search_count' => (int) $event->search_count,
            ]);
    }

    private function mostViewedBusinesses(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $views = AnalyticsEvent::query()
            ->select('organization_id', DB::raw('COUNT(*) as views'))
            ->where('event_type', 'business_view')
            ->whereNotNull('organization_id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('organization_id');

        $calls = AnalyticsEvent::query()
            ->select('organization_id', DB::raw('COUNT(*) as call_clicks'))
            ->where('event_type', 'call_click')
            ->whereNotNull('organization_id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('organization_id');

        return DB::query()
            ->fromSub($views, 'views')
            ->leftJoinSub($calls, 'calls', 'calls.organization_id', '=', 'views.organization_id')
            ->join('organizations', 'organizations.id', '=', 'views.organization_id')
            ->leftJoin('cities', 'cities.id', '=', 'organizations.city_id')
            ->orderByDesc('views.views')
            ->limit(10)
            ->get([
                'organizations.id as organization_id',
                'organizations.name as business_name',
                'cities.name as city_name',
                'views.views',
                DB::raw('COALESCE(calls.call_clicks, 0) as call_clicks'),
            ]);
    }

    private function mostClickedFields(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $views = AnalyticsEvent::query()
            ->select('football_field_id', DB::raw('COUNT(*) as views'))
            ->where('event_type', 'field_view')
            ->whereNotNull('football_field_id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('football_field_id');

        $calls = AnalyticsEvent::query()
            ->select('football_field_id', DB::raw('COUNT(*) as call_clicks'))
            ->where('event_type', 'call_click')
            ->whereNotNull('football_field_id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('football_field_id');

        return DB::query()
            ->fromSub($views, 'views')
            ->leftJoinSub($calls, 'calls', 'calls.football_field_id', '=', 'views.football_field_id')
            ->join('football_fields', 'football_fields.id', '=', 'views.football_field_id')
            ->join('organizations', 'organizations.id', '=', 'football_fields.organization_id')
            ->orderByDesc('views.views')
            ->limit(10)
            ->get([
                'football_fields.id as football_field_id',
                'football_fields.name as field_name',
                'organizations.name as business_name',
                'views.views',
                DB::raw('COALESCE(calls.call_clicks, 0) as call_clicks'),
            ]);
    }

    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
