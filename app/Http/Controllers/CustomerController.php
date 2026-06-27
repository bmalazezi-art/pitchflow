<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Services\ActivityLogger;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));
        $customers = Customer::query()->forOrganization($request->user()->organization_id)
            ->select('customers.*')
            ->addSelect([
                'outstanding_balance' => Reservation::query()
                    ->selectRaw('COALESCE(SUM(price - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['pending', 'confirmed', 'completed']),
            ])
            ->with([
                'preferredField:id,name',
                'notes' => fn ($query) => $query->with('user:id,name')->latest()->limit(10),
                'reservations' => fn ($query) => $query->with('footballField:id,name')->latest('starts_at')->limit(12),
            ])
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('phone_normalized', 'like', "%{$search}%");
            }))
            ->latest('last_visit_at')->paginate(20)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
            'fields' => FootballField::query()->forOrganization($request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Customers/Show', [
            'customer' => $customer->load([
                'preferredField:id,name',
                'notes' => fn ($query) => $query->with('user:id,name')->latest(),
                'reservations' => fn ($query) => $query->with('footballField:id,name')->latest('starts_at')->limit(50),
            ]),
            'fields' => FootballField::query()->forOrganization($customer->organization_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(
        CustomerRequest $request,
        Customer $customer,
        PhoneNormalizer $phones,
        ActivityLogger $activity,
    ): RedirectResponse {
        $this->authorize('update', $customer);
        $data = $request->validated();
        if ($data['preferred_field_id'] ?? null) {
            abort_unless(FootballField::query()->forOrganization($customer->organization_id)->whereKey($data['preferred_field_id'])->exists(), 422);
        }

        $customer->update([...$data, 'phone_normalized' => $phones->normalize($data['phone'])]);
        $activity->log('customer_updated', $customer);

        return back()->with('success', __('messages.customer_updated'));
    }
}
