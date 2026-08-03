import type { SlotStatus } from './slotStatus';

type PublicAnalyticsEvent =
    | 'public_home_view'
    | 'availability_search'
    | 'city_selected'
    | 'business_view'
    | 'field_view'
    | 'availability_slot_view'
    | 'call_click'
    | 'register_business_click'
    | 'login_click'
    | 'language_switch'
    | 'reset_search_click';

interface AnalyticsPayload {
    organization_id?: number | null;
    football_field_id?: number | null;
    city_id?: number | null;
    metadata?: Record<string, string | number | boolean | null | undefined>;
}

function visitorId() {
    const key = 'pitchflow_visitor_id';
    const existing = localStorage.getItem(key);
    if (existing) return existing;

    const next = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    localStorage.setItem(key, next);
    return next;
}

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function trackPublicEvent(event_type: PublicAnalyticsEvent, payload: AnalyticsPayload = {}) {
    const body = JSON.stringify({
        event_type,
        visitor_id: visitorId(),
        organization_id: payload.organization_id ?? null,
        football_field_id: payload.football_field_id ?? null,
        city_id: payload.city_id ?? null,
        metadata: payload.metadata ?? {},
    });

    void fetch('/analytics/events', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body,
        keepalive: true,
    }).catch(() => undefined);
}

export function slotStatusForAnalytics(status: SlotStatus | 'occupied') {
    return status === 'occupied' ? 'reserved' : status;
}
