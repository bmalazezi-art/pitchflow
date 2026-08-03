<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:80'],
        ]);
        $search = trim($filters['search'] ?? '');

        return Inertia::render('Admin/AuditLogs', [
            'logs' => ActivityLog::query()
                ->with(['user:id,name,role', 'organization:id,name'])
                ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
                ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                    $nested->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%"));
                }))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => ['search' => $search, 'action' => $filters['action'] ?? ''],
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
