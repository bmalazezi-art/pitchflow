<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;

class SubscriptionService
{
    public function syncForOrganization(Organization $organization): Subscription
    {
        $currentFieldCount = $organization->footballFields()->count();
        $fieldCount = $currentFieldCount > 0
            ? $currentFieldCount
            : max($organization->number_of_fields, 1);
        $tier = collect(config('plans.tiers'))->first(
            fn (array $plan) => $fieldCount >= $plan['min_fields']
                && ($plan['max_fields'] === null || $fieldCount <= $plan['max_fields'])
        );

        $organization->update([
            'number_of_fields' => $fieldCount,
            'subscription_plan' => $tier['label'],
        ]);

        return $organization->subscriptions()->updateOrCreate(
            ['status' => 'trial'],
            [
                'plan_name' => $tier['label'],
                'price' => $tier['monthly_price'],
                'billing_cycle' => 'monthly',
                'started_at' => now(),
            ],
        );
    }
}
