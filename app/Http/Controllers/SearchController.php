<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $q = $request->string('q')->toString();
        $organizationId = $request->user()->organization_id;

        return response()->json([
            'customers' => Customer::query()->forOrganization($organizationId)
                ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                ->limit(5)->get(['id', 'name', 'phone', 'reliability_status']),
            'reservations' => Reservation::query()->forOrganization($organizationId)
                ->where(fn ($query) => $query->where('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%"))
                ->limit(5)->get(['id', 'customer_name', 'customer_phone', 'starts_at']),
            'fields' => FootballField::query()->forOrganization($organizationId)->where('name', 'like', "%{$q}%")
                ->limit(5)->get(['id', 'name']),
            'employees' => $request->user()->isOwner()
                ? User::query()->where('organization_id', $organizationId)->where('role', 'employee')->where('name', 'like', "%{$q}%")->limit(5)->get(['id', 'name', 'email'])
                : [],
        ]);
    }
}
