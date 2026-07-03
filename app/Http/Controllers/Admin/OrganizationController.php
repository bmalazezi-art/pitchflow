<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Organization;
use App\Services\ActivityLogger;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'city' => $filters['city'] ?? '',
                'subscription' => $filters['subscription'] ?? '',
                'visibility' => $filters['visibility'] ?? '',
            ],
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
            'plans' => collect(config('plans.tiers'))->pluck('label')->values(),
            'summary' => Organization::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function update(
        Request $request,
        Organization $organization,
        ActivityLogger $activity,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,suspended']]);
        $status = OrganizationStatus::from($data['status']);
        $timestamps = [
            'approved_at' => $status === OrganizationStatus::Approved ? ($organization->approved_at ?? now()) : $organization->approved_at,
            'rejected_at' => $status === OrganizationStatus::Rejected ? now() : null,
            'suspended_at' => $status === OrganizationStatus::Suspended ? now() : null,
        ];
        $organization->update(['status' => $status, ...$timestamps]);
        if ($status === OrganizationStatus::Approved) {
            $subscriptions->syncForOrganization($organization);
        }
        if ($status === OrganizationStatus::Suspended) {
            DB::table('sessions')->whereIn('user_id', $organization->users()->pluck('id'))->delete();
        }
        $activity->log("organization_{$status->value}", $organization, organizationId: $organization->id);

        return back()->with('success', __('messages.organization_updated'));
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
}
