<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): Response
    {
        abort_unless($request->user()->isOwner(), 403);
        $organization = $request->user()->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $period = (string) $request->input('period', 'this_month');
        [$from, $to] = $this->periodRange($request, $period, $timezone);

        return Inertia::render('Reports/Index', [
            'report' => $reports->detailed($organization, $from, $to),
            'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'period' => $period],
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
