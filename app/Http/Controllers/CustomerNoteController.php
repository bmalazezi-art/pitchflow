<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerNoteRequest;
use App\Models\Customer;
use App\Models\CustomerNote;
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

    public function update(CustomerNoteRequest $request, Customer $customer, CustomerNote $note, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('addNote', $customer);
        abort_unless($note->customer_id === $customer->id && $note->organization_id === $customer->organization_id, 404);
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->update(['note' => $request->validated('note')]);
        $activity->log('customer_note_updated', $note);

        return back()->with('success', __('messages.note_updated'));
    }
}
