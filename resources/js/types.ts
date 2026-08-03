export type Role = 'super_admin' | 'owner' | 'employee';

export interface AuthUser {
    id: number;
    name: string;
    email: string | null;
    role: Role;
    preferred_language: 'en' | 'sq';
    status: 'invited' | 'active' | 'disabled';
    permissions: string[] | null;
}

export interface Organization {
    id: number;
    name: string;
    slug: string;
    status: string;
    timezone: string;
    currency: string;
}

export interface SharedProps {
    auth: { user: AuthUser | null; organization: Organization | null };
    locale: 'en' | 'sq';
    notifications: Array<{
        id: number;
        action: string;
        created_at: string;
        user?: { id: number; name: string } | null;
        read: boolean;
    }>;
    notification_unread_count: number;
    flash: {
        success?: string;
        error?: string;
        invite_url?: string;
        invite_link?: string;
        invite_notice?: string;
        reset_url?: string;
        reset_link?: string;
        reset_notice?: string;
        slot_suggestions?: Array<{ starts_at: string; ends_at: string; label: string }>;
        waiting_list_requests?: {
            count: number;
            field_name: string;
            start_time: string;
            end_time: string;
            requests: Array<{ id: number; customer_name: string; phone: string; email?: string | null; note?: string | null; created_at?: string; message: string }>;
        } | null;
        status_undo?: {
            organization_id: number;
            history_id: number;
            previous_status: string;
            new_status: string;
            message: string;
        };
    };
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
