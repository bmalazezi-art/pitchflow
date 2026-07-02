export type Role = 'super_admin' | 'owner' | 'employee';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
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
    flash: {
        success?: string;
        error?: string;
        slot_suggestions?: Array<{ starts_at: string; ends_at: string; label: string }>;
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
