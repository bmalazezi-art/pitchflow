<?php

namespace App\Http\Controllers;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Services\AvailabilityService;
use App\Services\OperatingScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAvailabilityController extends Controller
{
    public function __invoke(
        Request $request,
        AvailabilityService $availability,
        OperatingScheduleService $operatingSchedule,
    ): Response {
        $business = null;
        $pitchAvailability = [];
        $cityId = $request->integer('city');
        $clientNow = $request->input('client_now');
        $selectedDate = (string) $request->input('date', CarbonImmutable::now('Europe/Belgrade')->toDateString());
        $activeFields = fn ($query) => $query
            ->where('status', FieldStatus::Active)
            ->where(fn ($cityQuery) => $cityQuery
                ->where('city_id', $cityId)
                ->orWhereNull('city_id'));

        $businesses = Organization::query()
            ->when($request->filled('city'), fn ($query) => $query
                ->publiclyDiscoverable($cityId), fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'city:id,name',
                'footballFields' => fn ($query) => $activeFields($query)
                    ->orderBy('name')
                    ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour', 'opening_time', 'closing_time']),
                'footballFields.city:id,name',
                'footballFields.organization:id,timezone',
                'footballFields.operatingHours',
                'footballFields.operatingHourOverrides' => fn ($query) => $query
                    ->whereBetween('date', [now()->subDay()->toDateString(), now()->addDays(8)->toDateString()]),
            ])
            ->inPublicDirectoryOrder()
            ->get(['id', 'city_id', 'name', 'phone', 'address', 'number_of_fields', 'currency', 'amenities', 'approved_at'])
            ->each(function (Organization $organization) {
                $organization->setAttribute('is_new', $organization->isNewlyApproved());
                $organization->setAttribute('is_verified', true);
                $organization->makeHidden('approved_at');
            });
        $this->addOperatingStatus($businesses, $operatingSchedule);

        $recentBusinesses = Organization::query()
            ->eligibleForPublicDirectory()
            ->when($request->filled('city'), fn ($query) => $query->publiclyDiscoverable($cityId))
            ->with([
                'city:id,name',
                'footballFields' => fn ($query) => $query
                    ->where('status', FieldStatus::Active)
                    ->when($request->filled('city'), fn ($query) => $activeFields($query))
                    ->orderBy('name')
                    ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour', 'opening_time', 'closing_time']),
                'footballFields.organization:id,timezone',
                'footballFields.operatingHours',
                'footballFields.operatingHourOverrides' => fn ($query) => $query
                    ->whereBetween('date', [now()->subDay()->toDateString(), now()->addDays(8)->toDateString()]),
            ])
            ->latest('approved_at')
            ->limit(6)
            ->get(['id', 'city_id', 'name', 'phone', 'address', 'number_of_fields', 'currency', 'amenities', 'approved_at'])
            ->each(function (Organization $organization) {
                $organization->setAttribute('is_new', $organization->isNewlyApproved());
                $organization->setAttribute('is_verified', true);
                $organization->makeHidden('approved_at');
            });
        $this->addOperatingStatus($recentBusinesses, $operatingSchedule);

        $popularCities = City::query()
            ->select(['cities.id', 'cities.name'])
            ->join('organizations', 'organizations.city_id', '=', 'cities.id')
            ->join('football_fields', 'football_fields.organization_id', '=', 'organizations.id')
            ->where('cities.is_active', true)
            ->where('organizations.status', OrganizationStatus::Approved)
            ->whereNotNull('organizations.city_id')
            ->whereNotNull('organizations.phone')
            ->where('organizations.phone', '!=', '')
            ->whereNull('organizations.deleted_at')
            ->where('football_fields.status', FieldStatus::Active)
            ->whereNotNull('football_fields.opening_time')
            ->whereNotNull('football_fields.closing_time')
            ->whereNull('football_fields.deleted_at')
            ->selectRaw('COUNT(football_fields.id) as football_fields_count')
            ->groupBy('cities.id', 'cities.name')
            ->orderByDesc('football_fields_count')
            ->orderBy('cities.name')
            ->limit(6)
            ->get();

        $activePublicFields = FootballField::query()
            ->publicReady()
            ->whereHas('organization', fn ($query) => Organization::constrainEligibleForPublicDirectory($query));
        $statistics = [
            'football_fields' => (clone $activePublicFields)->count(),
            'cities' => City::query()
                ->where('is_active', true)
                ->whereHas('organizations', fn ($query) => Organization::constrainEligibleForPublicDirectory($query))
                ->count(),
            'registered_businesses' => Organization::query()->count(),
            'verified_businesses' => Organization::query()->where('status', OrganizationStatus::Approved)->count(),
        ];

        if ($request->filled(['city', 'business'])) {
            $business = Organization::query()
                ->whereKey($request->integer('business'))
                ->publiclyDiscoverable($cityId)
                ->with([
                    'city:id,name',
                    'footballFields' => fn ($query) => $activeFields($query)
                        ->orderBy('name')
                        ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour', 'opening_time', 'closing_time']),
                    'footballFields.city:id,name',
                    'footballFields.organization:id,timezone',
                ])
                ->first();

            if ($business) {
                $pitchAvailability = $business->footballFields->map(fn (FootballField $field) => [
                    'field' => $field,
                    'slots' => $availability->slots($field, $selectedDate, $clientNow),
                ])->values();
            }
        } elseif ($request->filled(['city', 'field'])) {
            $field = FootballField::query()
                ->whereKey($request->integer('field'))
                ->where('status', FieldStatus::Active)
                ->where(function ($cityQuery) use ($request) {
                    $cityQuery->where('city_id', $request->integer('city'))
                        ->orWhere(function ($organizationCityQuery) use ($request) {
                            $organizationCityQuery->whereNull('city_id')
                                ->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('city_id', $request->integer('city')));
                        });
                })
                ->whereHas('organization', fn ($organizationQuery) => $organizationQuery
                    ->where('status', OrganizationStatus::Approved)
                    ->where('city_id', $cityId))
                ->with(['city:id,name', 'organization:id,city_id,name,phone,number_of_fields,currency,timezone', 'organization.city:id,name'])
                ->first();
            if ($field) {
                $business = $field->organization->load('city:id,name');
                $pitchAvailability = [[
                    'field' => $field,
                    'slots' => $availability->slots($field, $selectedDate, $clientNow),
                ]];
            }
        }

        return Inertia::render('Public/Availability', [
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
            'businesses' => $businesses,
            'recentBusinesses' => $recentBusinesses,
            'popularCities' => $popularCities,
            'statistics' => $statistics,
            'selectedBusiness' => $business,
            'pitchAvailability' => $pitchAvailability,
            'filters' => [
                'city' => $request->integer('city') ?: null,
                'business' => $request->integer('business') ?: null,
                'date' => $selectedDate,
                'client_now' => $clientNow,
            ],
        ]);
    }

    /** @param Collection<int, Organization> $businesses */
    private function addOperatingStatus(Collection $businesses, OperatingScheduleService $schedule): void
    {
        $now = CarbonImmutable::now('UTC');

        foreach ($businesses as $business) {
            $statuses = $business->footballFields->map(fn (FootballField $field) => $schedule->statusAt($field, $now));
            $nextOpening = $statuses->pluck('opens_at')->filter()->sort()->first();
            $business->setAttribute('operating_status', [
                'is_open' => $statuses->contains('is_open', true),
                'opens_at' => $nextOpening?->format('H:i'),
            ]);
            $business->footballFields->each->makeHidden([
                'organization', 'operating_hours', 'operating_hour_overrides',
            ]);
        }
    }
}
