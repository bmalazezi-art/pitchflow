<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case LateCancelled = 'late_cancelled';
    case NoShow = 'no_show';
    case Voided = 'voided';

    public static function blockedValues(): array
    {
        return [
            self::Pending->value,
            self::Confirmed->value,
            self::Completed->value,
            self::LateCancelled->value,
            self::NoShow->value,
        ];
    }

    public static function freeValues(): array
    {
        return [
            self::Cancelled->value,
            self::Voided->value,
        ];
    }

    public function blocksAvailability(): bool
    {
        return in_array($this->value, self::blockedValues(), true);
    }
}
