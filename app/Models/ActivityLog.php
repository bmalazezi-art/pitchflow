<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'user_id', 'action', 'entity_type', 'entity_id',
        'description', 'properties', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Activity logs are immutable.'));
        static::deleting(fn () => throw new \LogicException('Activity logs are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
