<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $filters = $request->validate(['status' => ['nullable', 'in:open,in_progress,solved']]);

        return Inertia::render('Admin/SupportRequests', [
            'requests' => SupportRequest::query()
                ->with(['organization:id,name', 'user:id,name,email,phone'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => $filters['status'] ?? ''],
        ]);
    }

    public function update(Request $request, SupportRequest $supportRequest, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'solved'])]]);
        $supportRequest->update($data);
        $activity->log('support_request_updated', $supportRequest, organizationId: $supportRequest->organization_id, properties: $data);

        return back()->with('success', __('messages.support_request_updated'));
    }
}
