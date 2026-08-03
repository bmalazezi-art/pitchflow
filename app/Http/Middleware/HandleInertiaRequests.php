<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email', 'role', 'preferred_language', 'status', 'permissions']),
                'organization' => $user?->organization?->only(['id', 'name', 'slug', 'status', 'timezone', 'currency']),
            ],
            'locale' => app()->getLocale(),
            'notifications' => fn () => $user ? ActivityLog::query()
                ->with('user:id,name')
                ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('organization_id', $user->organization_id))
                ->whereIn('action', [
                    'reservation_created',
                    'reservation_cancelled',
                    'reservation_marked_paid',
                    'employee_created',
                    'employee_updated',
                    'settings_updated',
                    'organization_updated',
                ])
                ->latest()
                ->limit(6)
                ->get(['id', 'organization_id', 'user_id', 'action', 'created_at'])
                ->map(fn (ActivityLog $activity) => [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'created_at' => $activity->created_at?->toISOString(),
                    'user' => $activity->user?->only(['id', 'name']),
                    'read' => true,
                ])
                ->values() : [],
            'notification_unread_count' => 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'invite_url' => fn () => $request->session()->get('invite_url'),
                'invite_link' => fn () => $request->session()->get('invite_link'),
                'invite_notice' => fn () => $request->session()->get('invite_notice'),
                'reset_url' => fn () => $request->session()->get('reset_url'),
                'reset_link' => fn () => $request->session()->get('reset_link'),
                'reset_notice' => fn () => $request->session()->get('reset_notice'),
                'slot_suggestions' => fn () => $request->session()->get('slot_suggestions', []),
                'waiting_list_requests' => fn () => $request->session()->get('waiting_list_requests'),
                'status_undo' => fn () => $request->session()->get('status_undo'),
            ],
        ];
    }
}
