<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public const PUBLIC_EVENT_TYPES = [
        'public_home_view',
        'availability_search',
        'city_selected',
        'business_view',
        'field_view',
        'availability_slot_view',
        'call_click',
        'register_business_click',
        'login_click',
        'language_switch',
        'reset_search_click',
    ];

    protected $fillable = [
        'organization_id',
        'football_field_id',
        'city_id',
        'event_type',
        'visitor_id',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function footballField(): BelongsTo
    {
        return $this->belongsTo(FootballField::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
