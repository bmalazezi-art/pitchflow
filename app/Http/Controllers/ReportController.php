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
        $from = CarbonImmutable::parse($request->input('from', 'first day of this month'), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($request->input('to', 'last day of this month'), $timezone)->endOfDay();

        return Inertia::render('Reports/Index', [
            'report' => $reports->detailed($organization, $from, $to),
            'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        ]);
    }
}
