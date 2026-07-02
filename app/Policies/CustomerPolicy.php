<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenant;
use App\Support\EmployeePermissions;

class CustomerPolicy
{
    use ChecksTenant;

    public function view(User $user, Customer $customer): bool
    {
        if (! $this->sameOrganization($user, $customer)) {
            return false;
        }

        return $user->isOwner() || ($user->hasEmployeePermission(EmployeePermissions::VIEW_CUSTOMERS) && $customer->reservations()
            ->whereIn('football_field_id', $user->assignedFields()->select('football_fields.id'))
            ->exists());
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasEmployeePermission(EmployeePermissions::VIEW_CUSTOMERS) && $this->view($user, $customer);
    }

    public function addNote(User $user, Customer $customer): bool
    {
        return $user->hasEmployeePermission(EmployeePermissions::ADD_CUSTOMER_NOTES) && $this->view($user, $customer);
    }
}
