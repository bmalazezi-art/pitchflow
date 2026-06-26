<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_closed
 * @property string $opening_time
 * @property string $closing_time
 */
class OperatingHour extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'football_field_id', 'day_of_week',
        'opening_time', 'closing_time', 'is_closed',
    ];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }

    public function footballField(): BelongsTo
    {
        return $this->belongsTo(FootballField::class);
    }
}
