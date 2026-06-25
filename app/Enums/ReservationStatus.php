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

    public function blocksAvailability(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
