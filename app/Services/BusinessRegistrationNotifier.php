<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\BusinessApplicationReceived;
use App\Notifications\BusinessApproved;
use App\Notifications\BusinessReactivated;
use App\Notifications\BusinessRejected;
use App\Notifications\BusinessSuspended;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessRegistrationNotifier
{
    public function applicationReceived(User $owner): void
    {
        $organization = $owner->organization;

        if (! $organization) {
            return;
        }

        $this->notify($owner, new BusinessApplicationReceived($organization), 'PitchFlow: Business application received');
    }

    public function approved(Organization $organization): void
    {
        if ($owner = $this->ownerFor($organization)) {
            $this->notify($owner, new BusinessApproved($organization), 'PitchFlow: Business approved');
        }
    }

    public function rejected(Organization $organization, ?string $reason = null): void
    {
        if ($owner = $this->ownerFor($organization)) {
            $this->notify($owner, new BusinessRejected($organization, $reason), 'PitchFlow: Business application update');
        }
    }

    public function suspended(Organization $organization, ?string $reason = null): void
    {
        if ($owner = $this->ownerFor($organization)) {
            $this->notify($owner, new BusinessSuspended($organization, $reason), 'PitchFlow: Business suspended');
        }
    }

    public function reactivated(Organization $organization): void
    {
        if ($owner = $this->ownerFor($organization)) {
            $this->notify($owner, new BusinessReactivated($organization), 'PitchFlow: Business reactivated');
        }
    }

    private function ownerFor(Organization $organization): ?User
    {
        return $organization->users()
            ->where('role', 'owner')
            ->whereNotNull('email')
            ->orderBy('id')
            ->first();
    }

    private function notify(User $owner, object $notification, string $subject): void
    {
        if (blank($owner->email)) {
            return;
        }

        if ($this->mailSendingDisabled()) {
            Log::info('PitchFlow business status email prepared', [
                'subject' => $subject,
                'owner_id' => $owner->id,
                'owner_email' => $owner->email,
                'organization_id' => $owner->organization_id,
            ]);
        }

        try {
            $owner->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('PitchFlow business status email failed', [
                'subject' => $subject,
                'owner_id' => $owner->id,
                'owner_email' => $owner->email,
                'organization_id' => $owner->organization_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function mailSendingDisabled(): bool
    {
        return app()->environment(['local', 'development', 'testing'])
            || in_array(config('mail.default'), ['log', 'array'], true);
    }
}
