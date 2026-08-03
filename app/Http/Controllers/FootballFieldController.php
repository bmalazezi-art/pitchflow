<?php

namespace App\Http\Controllers;

use App\Http\Requests\FootballFieldRequest;
use App\Models\City;
use App\Models\FootballField;
use App\Services\ActivityLogger;
use App\Services\SubscriptionService;
use App\Support\EmployeePermissions;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FootballFieldController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_if($user->isEmployee() && ! $user->hasEmployeePermission(EmployeePermissions::VIEW_ASSIGNED_FIELDS), 403);
        $timezone = Timezones::resolve($user->organization->timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $assignedFieldIds = $user->isEmployee()
            ? $user->assignedFields()->pluck('football_fields.id')
            : null;
        $fields = FootballField::query()->forOrganization($user->organization_id)
            ->when($assignedFieldIds !== null, fn ($query) => $query->whereIn('id', $assignedFieldIds))
            ->with(['operatingHours', 'employees:id,name'])
            ->withCount([
                'reservations',
                'reservations as today_reservations_count' => fn ($query) => $query->whereBetween('starts_at', [$today->utc(), $today->endOfDay()->utc()]),
                'employees',
            ])->orderBy('name')->paginate(15);

        return Inertia::render('Fields/Index', [
            'fields' => $fields,
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
        ]);
    }

    public function store(
        FootballFieldRequest $request,
        ActivityLogger $activity,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorize('create', FootballField::class);
        $data = $request->validated();

        $field = DB::transaction(function () use ($request, $data) {
            $field = FootballField::create([
                ...$data,
                'organization_id' => $request->user()->organization_id,
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            ]);
            $this->syncHours($field, $data['operating_hours'] ?? []);

            return $field;
        });

        $activity->log('field_created', $field);
        $subscriptions->syncForOrganization($request->user()->organization);

        return back()->with('success', __('messages.field_created'));
    }

    public function update(FootballFieldRequest $request, FootballField $field, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $field);
        $data = $request->validated();

        if ($request->user()->isOwner()) {
            $data = collect($data)->only([
                'name', 'status', 'price_per_hour', 'opening_time', 'closing_time', 'operating_hours',
            ])->all();
        }

        DB::transaction(function () use ($field, $data) {
            $field->update($data);
            $this->syncHours($field, $data['operating_hours'] ?? []);
        });
        $activity->log('field_updated', $field);

        return back()->with('success', __('messages.field_updated'));
    }

    public function destroy(
        FootballField $field,
        ActivityLogger $activity,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorize('delete', $field);
        $field->delete();
        $activity->log('field_deleted', $field);
        $subscriptions->syncForOrganization(request()->user()->organization);

        return back()->with('success', __('messages.field_deleted'));
    }

    private function syncHours(FootballField $field, array $hours): void
    {
        if ($hours === []) {
            $hours = collect(range(0, 6))->map(fn (int $day) => [
                'day_of_week' => $day,
                'opening_time' => $field->opening_time,
                'closing_time' => $field->closing_time,
                'is_closed' => false,
            ])->all();
        }

        foreach ($hours as $hour) {
            $field->operatingHours()->updateOrCreate(
                ['day_of_week' => $hour['day_of_week']],
                [...$hour, 'organization_id' => $field->organization_id],
            );
        }
    }
}
