<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $action,
        ?Model $entity = null,
        ?string $description = null,
        array $properties = [],
        ?int $organizationId = null,
    ): ActivityLog {
        /** @var Request|null $request */
        $request = app()->bound('request') ? request() : null;
        $user = $request?->user();

        return ActivityLog::create([
            'organization_id' => $organizationId ?? $user?->organization_id,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
