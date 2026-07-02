<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenant;
use App\Support\EmployeePermissions;

class ReservationPolicy
{
    use ChecksTenant;

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->sameOrganization($user, $reservation)
            && ($user->isOwner() || $user->assignedFields()->whereKey($reservation->football_field_id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null && $user->hasEmployeePermission(EmployeePermissions::CREATE_RESERVATIONS);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->hasEmployeePermission(EmployeePermissions::EDIT_RESERVATIONS) && $this->view($user, $reservation);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasEmployeePermission(EmployeePermissions::CANCEL_RESERVATIONS) && $this->view($user, $reservation);
    }
}
