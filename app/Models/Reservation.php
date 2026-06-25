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

class Reservation extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'football_field_id', 'customer_id', 'customer_name',
        'customer_phone', 'starts_at', 'ends_at', 'status', 'payment_status',
        'price', 'paid_amount', 'currency', 'is_walk_in', 'notes',
        'cancellation_reason', 'created_by', 'updated_by', 'cancelled_by', 'cancelled_at',
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

    public function slots(): HasMany
    {
        return $this->hasMany(ReservationSlot::class);
    }
}
