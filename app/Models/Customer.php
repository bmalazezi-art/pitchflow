<?php

namespace App\Models;

use App\Enums\ReliabilityStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'preferred_field_id', 'name', 'phone', 'phone_normalized',
        'reliability_status', 'total_reservations', 'completed_reservations',
        'cancelled_reservations', 'late_cancellations', 'no_shows', 'last_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'reliability_status' => ReliabilityStatus::class,
            'last_visit_at' => 'datetime',
        ];
    }

    public function preferredField(): BelongsTo
    {
        return $this->belongsTo(FootballField::class, 'preferred_field_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }
}
