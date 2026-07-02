<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\EmployeePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $q = $request->string('q')->toString();
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json([
                'organizations' => Organization::query()
                    ->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    })
                    ->limit(8)
                    ->get(['id', 'name', 'email', 'phone', 'status']),
            ]);
        }

        $organizationId = $user->organization_id;
        $fieldIds = $user->isEmployee()
            ? $user->assignedFields()->pluck('football_fields.id')->all()
            : null;
        $canViewCustomers = ! $user->isEmployee() || $user->hasEmployeePermission(EmployeePermissions::VIEW_CUSTOMERS);
        $canViewFields = ! $user->isEmployee() || $user->hasEmployeePermission(EmployeePermissions::VIEW_ASSIGNED_FIELDS);

        return response()->json([
            'customers' => $canViewCustomers ? Customer::query()->forOrganization($organizationId)
                ->when($fieldIds !== null, fn ($query) => $query->whereHas('reservations', fn ($reservationQuery) => $reservationQuery->whereIn('football_field_id', $fieldIds)))
                ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                ->limit(5)->get(['id', 'name', 'phone', 'reliability_status']) : [],
            'reservations' => Reservation::query()->forOrganization($organizationId)
                ->when($fieldIds !== null, fn ($query) => $query->whereIn('football_field_id', $fieldIds))
                ->where(fn ($query) => $query->where('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%"))
                ->limit(5)->get(['id', 'customer_name', 'customer_phone', 'starts_at']),
            'fields' => $canViewFields ? FootballField::query()->forOrganization($organizationId)
                ->when($fieldIds !== null, fn ($query) => $query->whereIn('id', $fieldIds))
                ->where('name', 'like', "%{$q}%")
                ->limit(5)->get(['id', 'name']) : [],
            'employees' => $user->isOwner()
                ? User::query()->where('organization_id', $organizationId)->where('role', 'employee')->where('name', 'like', "%{$q}%")->limit(5)->get(['id', 'name', 'email'])
                : [],
        ]);
    }
}
