<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $football_field_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $cancelled_at
 * @property ReservationStatus $status
 * @property PaymentStatus $payment_status
 * @property-read Customer $customer
 * @property-read FootballField $footballField
 * @property-read User|null $cancelledByUser
 */
class Reservation extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'football_field_id', 'customer_id', 'customer_name',
        'customer_phone', 'starts_at', 'ends_at', 'status', 'payment_status',
        'price', 'paid_amount', 'currency', 'is_walk_in', 'notes',
        'cancellation_reason', 'previous_status', 'cancellation_note',
        'created_by', 'updated_by', 'cancelled_by', 'cancelled_by_user_id', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'price' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'is_walk_in' => 'boolean',
        ];
    }

    public function footballField(): BelongsTo
    {
        return $this->belongsTo(FootballField::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ReservationSlot::class);
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(ReservationCorrectionRequest::class);
    }

    public function waitingListRequests(): HasMany
    {
        return $this->hasMany(WaitingListRequest::class);
    }
}
