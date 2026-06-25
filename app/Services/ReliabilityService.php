<?php

namespace App\Services;

use App\Enums\ReliabilityStatus;
use App\Enums\ReservationStatus;
use App\Models\Customer;

class ReliabilityService
{
    public function recalculate(Customer $customer): Customer
    {
        $counts = $customer->reservations()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $completed = (int) ($counts[ReservationStatus::Completed->value] ?? 0);
        $cancelled = (int) ($counts[ReservationStatus::Cancelled->value] ?? 0);
        $late = (int) ($counts[ReservationStatus::LateCancelled->value] ?? 0);
        $noShows = (int) ($counts[ReservationStatus::NoShow->value] ?? 0);
        $total = (int) $counts->sum();

        $status = match (true) {
            $noShows >= 2 || $late >= 3 => ReliabilityStatus::HighRisk,
            $noShows >= 1 || $late >= 1 || ($total >= 3 && (($cancelled + $late) / $total) >= 0.3) => ReliabilityStatus::NeedsAttention,
            default => ReliabilityStatus::Reliable,
        };

        $customer->forceFill([
            'total_reservations' => $total,
            'completed_reservations' => $completed,
            'cancelled_reservations' => $cancelled,
            'late_cancellations' => $late,
            'no_shows' => $noShows,
            'reliability_status' => $status,
            'last_visit_at' => $customer->reservations()
                ->where('status', ReservationStatus::Completed->value)
                ->max('ends_at'),
        ])->save();

        return $customer->refresh();
    }
}
