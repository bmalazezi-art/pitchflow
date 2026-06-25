<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $employee): bool
    {
        return $user->isOwner()
            && $employee->role === UserRole::Employee
            && $user->organization_id === $employee->organization_id;
    }

    public function delete(User $user, User $employee): bool
    {
        return $this->update($user, $employee);
    }
}
