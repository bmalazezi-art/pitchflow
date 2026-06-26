<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_closed
 * @property string|null $opening_time
 * @property string|null $closing_time
 * @property Carbon $date
 */
class OperatingHourOverride extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'football_field_id', 'date', 'opening_time',
        'closing_time', 'is_closed', 'reason',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_closed' => 'boolean'];
    }

    public function footballField(): BelongsTo
    {
        return $this->belongsTo(FootballField::class);
    }
}
