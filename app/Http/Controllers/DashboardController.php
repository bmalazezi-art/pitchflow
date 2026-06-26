<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): Response|RedirectResponse
    {
        $user = request()->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.organizations');
        }

        return Inertia::render('Dashboard', [
            'metrics' => $reports->dashboard($user->organization),
        ]);
    }
}
