<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerNoteRequest;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class CustomerNoteController extends Controller
{
    public function store(CustomerNoteRequest $request, Customer $customer, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('addNote', $customer);
        $note = $customer->notes()->create([
            'organization_id' => $customer->organization_id,
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
        ]);
        $activity->log('customer_note_created', $note);

        return back()->with('success', __('messages.note_created'));
    }
}
