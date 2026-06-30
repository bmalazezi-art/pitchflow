<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
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
        $status = $request->input('status');

        return Inertia::render('Admin/Organizations', [
            'organizations' => Organization::query()->with(['city:id,name', 'latestSubscription'])
                ->withCount(['users', 'footballFields', 'reservations'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()->paginate(20)->withQueryString(),
            'filters' => ['status' => $status],
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
