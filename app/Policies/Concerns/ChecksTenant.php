<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenant
{
    private function sameOrganization(User $user, object $model): bool
    {
        return $user->organization_id !== null && $user->organization_id === $model->organization_id;
    }
}
