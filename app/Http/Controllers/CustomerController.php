<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Services\ActivityLogger;
use App\Services\PhoneNormalizer;
use App\Support\EmployeePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()->isEmployee() && ! $request->user()->hasEmployeePermission(EmployeePermissions::VIEW_CUSTOMERS), 403);
        $search = trim((string) $request->input('search'));
        $user = $request->user();
        $fieldIds = $user->isEmployee()
            ? $user->assignedFields()->pluck('football_fields.id')->all()
            : null;
        $customers = Customer::query()->forOrganization($user->organization_id)
            ->select('customers.*')
            ->addSelect([
                'outstanding_balance' => Reservation::query()
                    ->selectRaw('COALESCE(SUM(price - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereNull('deleted_at')
                    ->when($fieldIds !== null, fn ($query) => $query->whereIn('football_field_id', $fieldIds))
                    ->whereIn('status', ['pending', 'confirmed', 'completed']),
            ])
            ->withCount([
                'reservations as unpaid_reservations_count' => fn ($query) => $query
                    ->when($fieldIds !== null, fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds))
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->whereIn('status', ['pending', 'confirmed', 'completed']),
            ])
            ->when($fieldIds !== null, fn ($query) => $query->whereHas('reservations', fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds)))
            ->with([
                'preferredField:id,name',
                'notes' => fn ($query) => $query->with('user:id,name')->latest()->limit(10),
                'reservations' => fn ($query) => $query
                    ->when($fieldIds !== null, fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds))
                    ->with('footballField:id,name')->latest('starts_at')->limit(12),
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
            'fields' => FootballField::query()->forOrganization($user->organization_id)
                ->when($fieldIds !== null, fn ($query) => $query->whereIn('id', $fieldIds))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);
        $user = request()->user();
        $fieldIds = $user->isEmployee()
            ? $user->assignedFields()->pluck('football_fields.id')->all()
            : null;

        return Inertia::render('Customers/Show', [
            'customer' => $customer->loadCount([
                'reservations as unpaid_reservations_count' => fn ($query) => $query
                    ->when($fieldIds !== null, fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds))
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->whereIn('status', ['pending', 'confirmed', 'completed']),
            ])->load([
                'preferredField:id,name',
                'notes' => fn ($query) => $query->with('user:id,name')->latest(),
                'reservations' => fn ($query) => $query
                    ->when($fieldIds !== null, fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds))
                    ->with('footballField:id,name')->latest('starts_at')->limit(50),
            ]),
            'fields' => FootballField::query()->forOrganization($customer->organization_id)
                ->when($fieldIds !== null, fn ($query) => $query->whereIn('id', $fieldIds))
                ->orderBy('name')->get(['id', 'name']),
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
        $normalizedPhone = $phones->normalize($data['phone']);

        $duplicate = Customer::withTrashed()
            ->where('organization_id', $customer->organization_id)
            ->where('phone_normalized', $normalizedPhone)
            ->whereKeyNot($customer->id)
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone' => __('messages.customer_phone_taken'),
            ]);
        }

        if ($data['preferred_field_id'] ?? null) {
            $fieldAllowed = $request->user()->isEmployee()
                ? $request->user()->assignedFields()->whereKey($data['preferred_field_id'])->exists()
                : FootballField::query()
                    ->forOrganization($customer->organization_id)
                    ->whereKey($data['preferred_field_id'])
                    ->exists();

            abort_unless($fieldAllowed, 422);
        }

        $updates = [
            ...$data,
            'phone_normalized' => $normalizedPhone,
        ];

        if (array_key_exists('reliability_status', $data) && $data['reliability_status'] !== null) {
            $updates['reliability_status_manual'] = true;
        }

        $customer->update($updates);
        $activity->log('customer_updated', $customer);

        return back()->with('success', __('messages.profile_saved_successfully'));
    }
}
