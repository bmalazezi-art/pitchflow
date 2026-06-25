<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): Response
    {
        $organization = request()->user()->organization;

        return Inertia::render('Dashboard', [
            'metrics' => $reports->dashboard($organization),
        ]);
    }
}
