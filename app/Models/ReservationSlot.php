<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSlot extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'football_field_id', 'reservation_id', 'starts_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
