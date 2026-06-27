<?php

namespace App\Policies;

use App\Models\FootballField;
use App\Models\User;
use App\Policies\Concerns\ChecksTenant;

class FootballFieldPolicy
{
    use ChecksTenant;

    public function view(User $user, FootballField $field): bool
    {
        return $this->sameOrganization($user, $field)
            && ($user->isOwner() || $user->assignedFields()->whereKey($field->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, FootballField $field): bool
    {
        return $user->isOwner() && $this->sameOrganization($user, $field);
    }

    public function delete(User $user, FootballField $field): bool
    {
        return $user->isSuperAdmin() && $this->sameOrganization($user, $field);
    }
}
