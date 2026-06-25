<?php

namespace App\Http\Controllers;

use App\Http\Requests\FootballFieldRequest;
use App\Models\City;
use App\Models\FootballField;
use App\Services\ActivityLogger;
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
        $fields = FootballField::query()->forOrganization($user->organization_id)
            ->withCount(['reservations', 'employees'])->orderBy('name')->paginate(15);

        return Inertia::render('Fields/Index', [
            'fields' => $fields,
            'cities' => City::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(FootballFieldRequest $request, ActivityLogger $activity): RedirectResponse
    {
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

        return back()->with('success', __('messages.field_created'));
    }

    public function update(FootballFieldRequest $request, FootballField $footballField, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $footballField);
        $data = $request->validated();

        DB::transaction(function () use ($footballField, $data) {
            $footballField->update($data);
            $this->syncHours($footballField, $data['operating_hours'] ?? []);
        });
        $activity->log('field_updated', $footballField);

        return back()->with('success', __('messages.field_updated'));
    }

    public function destroy(FootballField $footballField, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('delete', $footballField);
        $footballField->delete();
        $activity->log('field_deleted', $footballField);

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
