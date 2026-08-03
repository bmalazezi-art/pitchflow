<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingListRequest extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'football_field_id',
        'reservation_id',
        'date',
        'start_time',
        'end_time',
        'customer_name',
        'phone',
        'email',
        'note',
        'status',
        'notified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'notified_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
