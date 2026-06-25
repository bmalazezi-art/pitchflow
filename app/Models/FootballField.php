<?php

namespace App\Models;

use App\Enums\FieldStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FootballField extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'city_id', 'name', 'slug', 'address', 'status',
        'price_per_hour', 'opening_time', 'closing_time',
    ];

    protected function casts(): array
    {
        return ['status' => FieldStatus::class, 'price_per_hour' => 'decimal:2'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_field_assignments')->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }
}
