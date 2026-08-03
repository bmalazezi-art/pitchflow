<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportRequestController extends Controller
{
    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $support = SupportRequest::query()->create([
            'organization_id' => $request->user()->organization_id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'status' => 'open',
        ]);
        $activity->log('support_request_created', $support, organizationId: $request->user()->organization_id);

        return back()->with('success', __('messages.support_request_created'));
    }
}
