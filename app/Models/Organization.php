<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $city_id
 * @property OrganizationStatus $status
 * @property string $timezone
 * @property string $currency
 * @property int $number_of_fields
 * @property int $cancellation_window_minutes
 * @property Carbon|null $approved_at
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'city_id', 'name', 'slug', 'email', 'phone', 'address', 'status',
        'subscription_plan', 'number_of_fields', 'timezone', 'currency',
        'locale', 'cancellation_window_minutes', 'approved_at', 'rejected_at',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<FootballField, $this> */
    public function footballFields(): HasMany
    {
        return $this->hasMany(FootballField::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
}
