<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenant;

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
        return $user->isEmployee() && $user->organization_id !== null;
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->isEmployee() && $this->view($user, $reservation);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->isEmployee() && $this->view($user, $reservation);
    }
}
