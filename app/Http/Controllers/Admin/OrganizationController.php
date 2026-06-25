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
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $status = $request->input('status');

        return Inertia::render('Admin/Organizations', [
            'organizations' => Organization::query()->with('city:id,name')
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
            'approved_at' => $status === OrganizationStatus::Approved ? now() : $organization->approved_at,
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
}
