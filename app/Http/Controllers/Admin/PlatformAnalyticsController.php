<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAnalyticsController extends Controller
{
    public function __invoke(Request $request, PlatformAnalyticsService $analytics): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $timezone = 'Europe/Belgrade';
        $period = (string) $request->input('period', 'this_month');
        [$from, $to] = $this->periodRange($request, $period, $timezone);

        return Inertia::render('Admin/PlatformAnalytics', [
            'analytics' => $analytics->report($from, $to),
            'filters' => [
                'period' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    private function periodRange(Request $request, string $period, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone);
        if ($request->filled(['from', 'to'])) {
            return [
                CarbonImmutable::parse($request->input('from'), $timezone)->startOfDay(),
                CarbonImmutable::parse($request->input('to'), $timezone)->endOfDay(),
            ];
        }

        return match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'yesterday' => [$today->subDay()->startOfDay(), $today->subDay()->endOfDay()],
            'this_week' => [$today->startOfWeek()->startOfDay(), $today->endOfWeek()->endOfDay()],
            'last_week' => [$today->subWeek()->startOfWeek()->startOfDay(), $today->subWeek()->endOfWeek()->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($request->input('from', $today->startOfMonth()->toDateString()), $timezone)->startOfDay(),
                CarbonImmutable::parse($request->input('to', $today->endOfMonth()->toDateString()), $timezone)->endOfDay(),
            ],
            default => [$today->startOfMonth()->startOfDay(), $today->endOfMonth()->endOfDay()],
        };
    }
}
