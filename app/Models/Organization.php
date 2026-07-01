<?php

namespace App\Models;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $featured_from
 * @property Carbon|null $featured_until
 * @property array<int, string>|null $amenities
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'city_id', 'name', 'slug', 'email', 'phone', 'address', 'amenities', 'status',
        'subscription_plan', 'number_of_fields', 'timezone', 'currency',
        'locale', 'cancellation_window_minutes', 'approved_at', 'rejected_at',
        'suspended_at', 'featured_from', 'featured_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'amenities' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'featured_from' => 'datetime',
            'featured_until' => 'datetime',
        ];
    }

    public function scopePubliclyDiscoverable(Builder $query, int $cityId): Builder
    {
        return $this->scopeEligibleForPublicDirectory($query)
            ->where('city_id', $cityId)
            ->whereHas('footballFields', fn (Builder $fieldQuery) => $fieldQuery
                ->where(fn (Builder $cityQuery) => $cityQuery
                    ->where('city_id', $cityId)
                    ->orWhereNull('city_id')));
    }

    public function scopeEligibleForPublicDirectory(Builder $query): Builder
    {
        return $query
            ->where('status', OrganizationStatus::Approved)
            ->whereHas('footballFields', fn (Builder $fieldQuery) => $fieldQuery
                ->where('status', FieldStatus::Active));
    }

    public function scopeInPublicDirectoryOrder(Builder $query, ?CarbonImmutable $at = null): Builder
    {
        $at ??= CarbonImmutable::now();

        return $query
            ->orderByRaw(
                'CASE WHEN featured_from IS NOT NULL AND featured_from <= ? AND featured_until IS NOT NULL AND featured_until >= ? THEN 0 WHEN approved_at >= ? THEN 1 ELSE 2 END',
                [$at, $at, $at->subDays(30)],
            )
            ->orderBy('name');
    }

    public function isNewlyApproved(?CarbonImmutable $at = null): bool
    {
        if ($this->approved_at === null) {
            return false;
        }

        $at ??= CarbonImmutable::now();

        return $this->approved_at->greaterThanOrEqualTo($at->subDays(30));
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
