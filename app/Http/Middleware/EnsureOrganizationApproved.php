<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user?->organization || $user->organization->status !== OrganizationStatus::Approved) {
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
