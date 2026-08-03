<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BusinessRegistrationNotifier;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'city' => ['nullable', 'integer', 'exists:cities,id'],
            'subscription' => ['nullable', 'string', Rule::in(collect(config('plans.tiers'))->pluck('label')->all())],
            'visibility' => ['nullable', 'in:visible,hidden,pending,missing'],
        ]);
        $search = trim($filters['search'] ?? '');

        return Inertia::render('Admin/Organizations', [
            'organizations' => Organization::query()
                ->with([
                    'city:id,name',
                    'latestSubscription',
                    'adminNotes' => fn ($query) => $query->with('user:id,name')->latest()->limit(5),
                    'statusHistories' => fn ($query) => $query->with('user:id,name')->latest()->limit(10),
                    'users' => fn ($query) => $query->where('role', UserRole::Owner)->select('id', 'organization_id', 'name', 'email', 'phone'),
                ])
                ->withCount([
                    'users', 'footballFields', 'reservations',
                    'footballFields as active_football_fields_count' => fn ($query) => $query->where('status', FieldStatus::Active),
                ])
                ->withMin('footballFields', 'price_per_hour')
                ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('users', fn ($users) => $users
                            ->where('role', UserRole::Owner)
                            ->where(fn ($owner) => $owner->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['city'] ?? null, fn ($query, $cityId) => $query->where('city_id', $cityId))
                ->when($filters['subscription'] ?? null, fn ($query, $plan) => $query->where('subscription_plan', $plan))
                ->when($filters['visibility'] ?? null, function ($query, $visibility) {
                    match ($visibility) {
                        'visible' => $query->eligibleForPublicDirectory(),
                        'pending' => $query->where('status', OrganizationStatus::Pending),
                        'missing' => $query->where('status', OrganizationStatus::Approved)
                            ->where(fn ($missing) => $missing->whereNull('city_id')->orWhereNull('address')->orWhere('address', '')->orWhereDoesntHave('footballFields', fn ($fields) => $fields->where('status', FieldStatus::Active))),
                        default => $query->whereIn('status', [OrganizationStatus::Suspended, OrganizationStatus::Rejected]),
                    };
                })
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [OrganizationStatus::Pending->value])
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Organization $organization) => [
                    ...$organization->toArray(),
                    'visibility_checklist' => $this->visibilityChecklist($organization),
                    'health_status' => $this->healthStatus($organization),
                ]),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'city' => $filters['city'] ?? '',
                'subscription' => $filters['subscription'] ?? '',
                'visibility' => $filters['visibility'] ?? '',
            ],
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
            'plans' => collect(config('plans.tiers'))->pluck('label')->values(),
            'summary' => Organization::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function store(
        Request $request,
        ActivityLogger $activity,
        SubscriptionService $subscriptions,
        PhoneNormalizer $phones,
    ): RedirectResponse {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_phone' => ['required', 'string', 'max:32'],
            'owner_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'public_phone' => ['required', 'string', 'max:32'],
            'number_of_fields' => ['required', 'integer', 'min:1', 'max:20'],
            'starting_price_per_hour' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'status' => ['required', 'in:approved,pending'],
        ]);

        $token = Str::random(64);
        $organization = DB::transaction(function () use ($data, $request, $activity, $subscriptions, $phones, $token) {
            $status = OrganizationStatus::from($data['status']);
            $organization = Organization::query()->create([
                'city_id' => $data['city_id'],
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'email' => $data['owner_email'] ?? 'manual-'.Str::uuid().'@pitchflow.local',
                'phone' => $data['public_phone'],
                'address' => $data['address'] ?? null,
                'number_of_fields' => $data['number_of_fields'],
                'status' => $status,
                'timezone' => 'Europe/Pristina',
                'currency' => 'EUR',
                'locale' => 'sq',
                'cancellation_window_minutes' => 120,
                'approved_at' => $status === OrganizationStatus::Approved ? now() : null,
            ]);

            $owner = User::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'] ?? null,
                'phone' => $data['owner_phone'],
                'phone_normalized' => $phones->normalize($data['owner_phone']),
                'password' => null,
                'role' => UserRole::Owner,
                'status' => 'invited',
                'preferred_language' => 'sq',
                'invited_at' => now(),
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => now()->addDays(7),
            ]);

            foreach (range(1, $data['number_of_fields']) as $index) {
                $field = FootballField::query()->create([
                    'organization_id' => $organization->id,
                    'city_id' => $organization->city_id,
                    'name' => $data['number_of_fields'] === 1 ? $organization->name : "{$organization->name} - Field {$index}",
                    'slug' => "field-{$index}",
                    'address' => $organization->address,
                    'status' => FieldStatus::Active,
                    'price_per_hour' => $data['starting_price_per_hour'] ?? 0,
                    'opening_time' => '12:00',
                    'closing_time' => '00:00',
                ]);

                foreach (range(0, 6) as $day) {
                    $field->operatingHours()->create([
                        'organization_id' => $organization->id,
                        'day_of_week' => $day,
                        'opening_time' => '12:00',
                        'closing_time' => '00:00',
                        'is_closed' => false,
                    ]);
                }
            }

            if ($status === OrganizationStatus::Approved) {
                $subscriptions->syncForOrganization($organization);
            }

            $activity->log('organization_created', $organization, organizationId: $organization->id);
            $activity->log('owner_invited', $owner, organizationId: $organization->id);

            return $organization;
        });

        return back()
            ->with('success', __('messages.organization_created'))
            ->with('invite_url', url("/employee/invite/{$token}"))
            ->with('invite_notice', __('messages.employee_invitation_created_copy_link'));
    }

    public function storeNote(Request $request, Organization $organization, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $note = $organization->adminNotes()->create([
            'user_id' => $request->user()->id,
            'note' => $data['note'],
        ]);
        $activity->log('organization_admin_note_created', $note, organizationId: $organization->id);

        return back()->with('success', __('messages.note_created'));
    }

    public function update(
        Request $request,
        Organization $organization,
        ActivityLogger $activity,
        SubscriptionService $subscriptions,
        BusinessRegistrationNotifier $notifier,
    ): RedirectResponse {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrganizationStatus::class)],
            'reason' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), [
                    OrganizationStatus::Rejected->value,
                    OrganizationStatus::Suspended->value,
                ], true) && blank($request->input('rejection_reason'))),
                'nullable',
                'string',
                'max:1000',
            ],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $organization->status;
        $status = OrganizationStatus::from($data['status']);
        $reason = $data['reason'] ?? $data['rejection_reason'] ?? null;

        if ($previousStatus === $status) {
            return back()->with('success', __('messages.organization_updated'));
        }

        $timestamps = [
            'approved_at' => $status === OrganizationStatus::Approved ? ($organization->approved_at ?? now()) : $organization->approved_at,
            'rejected_at' => $status === OrganizationStatus::Rejected ? now() : null,
            'suspended_at' => $status === OrganizationStatus::Suspended ? now() : null,
        ];

        $organization->update(['status' => $status, ...$timestamps]);
        $history = $organization->statusHistories()->create([
            'user_id' => $request->user()->id,
            'previous_status' => $previousStatus->value,
            'new_status' => $status->value,
            'reason' => $reason,
        ]);

        if ($status === OrganizationStatus::Approved) {
            $subscriptions->syncForOrganization($organization);
        }
        if ($status === OrganizationStatus::Suspended) {
            DB::table('sessions')->whereIn('user_id', $organization->users()->pluck('id'))->delete();
        }
        $activity->log("organization_{$status->value}", $organization, organizationId: $organization->id);

        if ($status === OrganizationStatus::Approved) {
            if ($previousStatus === OrganizationStatus::Suspended) {
                $notifier->reactivated($organization->refresh());
            } else {
                $notifier->approved($organization->refresh());
            }
        } elseif ($status === OrganizationStatus::Rejected) {
            $notifier->rejected($organization->refresh(), $reason);
        } elseif ($status === OrganizationStatus::Suspended) {
            $notifier->suspended($organization->refresh(), $reason);
        }

        return back()
            ->with('success', __('messages.organization_status_changed', ['status' => __("messages.organization_status_{$status->value}")]))
            ->with('status_undo', [
                'organization_id' => $organization->id,
                'history_id' => $history->id,
                'previous_status' => $previousStatus->value,
                'new_status' => $status->value,
                'message' => __('messages.organization_status_toast', ['status' => __("messages.organization_status_{$status->value}")]),
            ]);
    }

    public function updateSubscription(
        Request $request,
        Organization $organization,
        ActivityLogger $activity,
    ): RedirectResponse {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $planNames = collect(config('plans.tiers'))->pluck('label')->all();
        $data = $request->validate([
            'plan_name' => ['required', 'string', Rule::in($planNames)],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'status' => ['required', 'in:active,trial,expired,cancelled'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $subscription = $organization->latestSubscription;
        if ($subscription) {
            $subscription->update($data);
        } else {
            $subscription = $organization->subscriptions()->create([
                ...$data,
                'started_at' => now(),
            ]);
        }
        $organization->update(['subscription_plan' => $data['plan_name']]);
        $activity->log('subscription_updated', $subscription, organizationId: $organization->id);

        return back()->with('success', __('messages.subscription_updated'));
    }

    private function visibilityChecklist(Organization $organization): array
    {
        $hasActiveField = (int) ($organization->active_football_fields_count ?? 0) > 0;
        $hasWorkingHours = $organization->footballFields()
            ->publicReady()
            ->exists();
        $items = [
            ['key' => 'approved', 'complete' => $organization->status === OrganizationStatus::Approved],
            ['key' => 'notSuspended', 'complete' => $organization->status !== OrganizationStatus::Suspended],
            ['key' => 'activeField', 'complete' => $hasActiveField],
            ['key' => 'publicPhone', 'complete' => filled($organization->phone)],
            ['key' => 'city', 'complete' => filled($organization->city_id)],
            ['key' => 'workingHours', 'complete' => $hasWorkingHours],
        ];

        return [
            'is_public' => collect($items)->every('complete'),
            'items' => $items,
            'warnings' => collect($items)->reject('complete')->pluck('key')->values(),
        ];
    }

    private function healthStatus(Organization $organization): string
    {
        $checklist = $this->visibilityChecklist($organization);
        $activeFields = (int) ($organization->active_football_fields_count ?? 0);
        $employeeCount = max(0, (int) ($organization->users_count ?? 0) - 1);
        $recentReservations = $organization->reservations()->where('created_at', '>=', now()->subDays(30))->exists();

        if ($organization->status === OrganizationStatus::Suspended || ($recentReservations === false && $organization->reservations_count > 0)) {
            return 'at_risk';
        }

        if ($checklist['is_public'] && $activeFields > 0 && $employeeCount > 0) {
            return 'ready';
        }

        if (! $recentReservations && $activeFields === 0) {
            return 'inactive';
        }

        return 'needs_setup';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $counter = 2;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
