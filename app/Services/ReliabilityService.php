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

        $score = max(0, min(100, 100 - ($noShows * 30) - ($late * 20) - ($cancelled * 5)));
        $status = match (true) {
            $score < 50 => ReliabilityStatus::HighRisk,
            $score < 80 => ReliabilityStatus::NeedsAttention,
            default => ReliabilityStatus::Reliable,
        };

        $updates = [
            'total_reservations' => $total,
            'completed_reservations' => $completed,
            'cancelled_reservations' => $cancelled,
            'late_cancellations' => $late,
            'no_shows' => $noShows,
            'reliability_score' => $score,
            'last_visit_at' => $customer->reservations()
                ->where('status', ReservationStatus::Completed->value)
                ->max('ends_at'),
        ];

        if (! $customer->reliability_status_manual) {
            $updates['reliability_status'] = $status;
        }

        $customer->forceFill($updates)->save();

        return $customer->refresh();
    }
}
