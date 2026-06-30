<?php

namespace App\Http\Controllers;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): Response
    {
        $business = null;
        $pitchAvailability = [];
        $cityId = $request->integer('city');
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
                    ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour']),
                'footballFields.city:id,name',
            ])
            ->inPublicDirectoryOrder()
            ->get(['id', 'city_id', 'name', 'phone', 'address', 'number_of_fields', 'currency', 'amenities', 'approved_at'])
            ->each(function (Organization $organization) {
                $organization->setAttribute('is_new', $organization->isNewlyApproved());
                $organization->setAttribute('is_verified', true);
                $organization->makeHidden('approved_at');
            });

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
                    'slots' => $availability->slots($field, $request->input('date', now()->toDateString())),
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
                    'slots' => $availability->slots($field, $request->input('date', now()->toDateString())),
                ]];
            }
        }

        return Inertia::render('Public/Availability', [
            'cities' => City::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'businesses' => $businesses,
            'selectedBusiness' => $business,
            'pitchAvailability' => $pitchAvailability,
            'filters' => [
                'city' => $request->integer('city') ?: null,
                'business' => $request->integer('business') ?: null,
                'date' => $request->input('date', now()->toDateString()),
            ],
        ]);
    }
}
