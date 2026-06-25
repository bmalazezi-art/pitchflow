<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenant;

class CustomerPolicy
{
    use ChecksTenant;

    public function view(User $user, Customer $customer): bool
    {
        return $this->sameOrganization($user, $customer);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
