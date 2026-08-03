<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAnalyticsEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', Rule::in(AnalyticsEvent::PUBLIC_EVENT_TYPES)],
            'visitor_id' => ['nullable', 'string', 'max:80'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'football_field_id' => ['nullable', 'integer', 'exists:football_fields,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => $data['event_type'],
            'visitor_id' => $data['visitor_id'] ?? null,
            'organization_id' => $data['organization_id'] ?? null,
            'football_field_id' => $data['football_field_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'ip_hash' => $this->hashNullable($request->ip()),
            'user_agent_hash' => $this->hashNullable($request->userAgent()),
            'metadata' => $this->cleanMetadata($data['metadata'] ?? []),
        ]);

        return response()->json(['ok' => true]);
    }

    private function hashNullable(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', $value, config('app.key'));
    }

    private function cleanMetadata(array $metadata): array
    {
        return collect($metadata)
            ->take(20)
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : null)
            ->filter(fn ($value) => $value !== null)
            ->all();
    }
}
