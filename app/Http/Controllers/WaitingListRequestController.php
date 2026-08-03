<?php

namespace App\Http\Controllers;

use App\Models\WaitingListRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WaitingListRequestController extends Controller
{
    public function markNotified(Request $request, WaitingListRequest $waitingListRequest): RedirectResponse
    {
        abort_unless($request->user()->organization_id === $waitingListRequest->organization_id, 403);
        abort_if($request->user()->isEmployee() && ! $request->user()->assignedFields()->whereKey($waitingListRequest->football_field_id)->exists(), 403);

        $waitingListRequest->forceFill([
            'status' => 'notified',
            'notified_at' => now(),
        ])->save();

        return back()->with('success', __('messages.waiting_list_marked_notified'));
    }
}
