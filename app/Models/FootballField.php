<?php

namespace App\Models;

use App\Enums\FieldStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property FieldStatus $status
 * @property string $opening_time
 * @property string $closing_time
 * @property string $price_per_hour
 * @property-read Organization $organization
 */
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

    /** @param Builder<FootballField> $query */
    public function scopePublicReady(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [FieldStatus::Active, FieldStatus::Closed, FieldStatus::Maintenance])
            ->where(function (Builder $scheduleQuery) {
                $scheduleQuery
                    ->where(fn (Builder $fieldSchedule) => $fieldSchedule
                        ->whereNotNull('opening_time')
                        ->whereNotNull('closing_time'))
                    ->orWhereHas('operatingHours', fn (Builder $hours) => $hours
                        ->where('is_closed', false)
                        ->whereNotNull('opening_time')
                        ->whereNotNull('closing_time'));
            });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_field_assignments')->withTimestamps();
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return HasMany<OperatingHour, $this> */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }

    /** @return HasMany<OperatingHourOverride, $this> */
    public function operatingHourOverrides(): HasMany
    {
        return $this->hasMany(OperatingHourOverride::class);
    }
}
