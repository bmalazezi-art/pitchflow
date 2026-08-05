import { Link, router, usePage } from '@inertiajs/react';
import { Activity, BarChart3, Bell, Building2, CalendarDays, Check, ChevronDown, CircleUserRound, CreditCard, LayoutDashboard, LogOut, Menu, Moon, Search, Settings, Sun, Users, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import clsx from 'clsx';
import { setClientLocale, useLocale, useTranslation } from '../lib/i18n';
import { logoutAndReplace } from '../lib/logout';
import type { SharedProps } from '../types';
import GlobalSearch from '../Components/GlobalSearch';

export default function AppLayout({ children, title }: { children: ReactNode; title: string }) {
    const { auth, flash, notifications = [], notification_unread_count = 0 } = usePage<SharedProps>().props;
    const t = useTranslation();
    const locale = useLocale();
    const [open, setOpen] = useState(false);
    const [dark, setDark] = useState(() => localStorage.getItem('theme') === 'dark');
    const [searchOpen, setSearchOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [languageOpen, setLanguageOpen] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);
    const employeePermissions = auth.user?.permissions ?? ['create_reservations', 'edit_reservations', 'cancel_reservations', 'view_customers', 'add_customer_notes', 'view_calendar', 'view_assigned_fields'];
    const can = (permission: string) => employeePermissions.includes(permission);
    const roleLabel = auth.user?.role === 'super_admin' ? t('superAdmin') : auth.user?.role === 'owner' ? t('owner') : t('employee');
    const settingsHref = auth.user?.role === 'owner' ? '/settings/organization' : null;
    const languageOptions = [
        { code: 'en' as const, short: 'EN', label: 'English', flag: '🇬🇧' },
        { code: 'sq' as const, short: 'SQ', label: 'Shqip', flag: '🇦🇱' },
    ];
    const activeLanguage = languageOptions.find(option => option.code === locale) ?? languageOptions[1];
    const notificationLabel = (action: string) => {
        const labels: Record<string, string> = {
            reservation_created: 'activityReservationCreated',
            reservation_cancelled: 'activityReservationCancelled',
            reservation_marked_paid: 'activityReservationMarkedPaid',
            employee_created: 'activityEmployeeCreated',
            employee_updated: 'activityEmployeeUpdated',
            settings_updated: 'activitySettingsUpdated',
            organization_updated: 'activityBusinessUpdated',
        };

        return labels[action] ? t(labels[action] as any) : action.replaceAll('_', ' ');
    };
    const notificationIcon = (action: string) => action === 'reservation_marked_paid' ? <CreditCard size={15} /> : action.startsWith('employee_') ? <Users size={15} /> : <Activity size={15} />;
    const relativeTime = (value: string) => new Intl.RelativeTimeFormat(locale === 'sq' ? 'sq-AL' : 'en', { numeric: 'auto' }).format(
        Math.max(-7, Math.round((new Date(value).getTime() - Date.now()) / 86400000)),
        'day',
    );
    const switchLocale = (nextLocale: 'en' | 'sq') => {
        if (nextLocale === locale) return;
        setLanguageOpen(false);
        setClientLocale(nextLocale);
        router.post('/locale', { locale: nextLocale }, { preserveScroll: true, preserveState: false, replace: true });
    };
    const handleLogout = () => logoutAndReplace({
        onStart: () => {
            setUserMenuOpen(false);
            setLoggingOut(true);
        },
    });

    useEffect(() => {
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    }, [dark]);

    useEffect(() => {
        if (! auth.user) {
            window.location.replace('/login');
        }
    }, [auth.user]);

    if (! auth.user) {
        return null;
    }

    const nav = auth.user?.role === 'super_admin'
        ? [
            { label: t('organizations'), href: '/admin/organizations', icon: Building2 },
            { label: t('platformAnalytics'), href: '/admin/analytics', icon: BarChart3 },
            { label: t('supportRequests'), href: '/admin/support-requests', icon: Users },
            { label: t('auditLogs'), href: '/admin/audit-logs', icon: Activity },
            { label: 'Cities', href: '/admin/cities', icon: Settings },
        ]
        : auth.user?.role === 'employee'
            ? [
                { label: t('dashboard'), href: '/dashboard', icon: LayoutDashboard },
                ...(can('view_calendar') ? [{ label: t('bookingBoard'), href: '/calendar', icon: CalendarDays }] : []),
                { label: t('reservations'), href: '/reservations', icon: BarChart3 },
                ...(can('view_customers') ? [{ label: t('customers'), href: '/customers', icon: CircleUserRound }] : []),
                ...(can('view_assigned_fields') ? [{ label: t('fields'), href: '/fields', icon: Building2 }] : []),
                { label: t('myProfile'), href: '/profile', icon: CircleUserRound },
            ]
        : [
            { label: t('dashboard'), href: '/dashboard', icon: LayoutDashboard },
            { label: t('calendar'), href: '/calendar', icon: CalendarDays },
            { label: t('reservations'), href: '/reservations', icon: BarChart3 },
            { label: t('customers'), href: '/customers', icon: CircleUserRound },
            { label: t('fields'), href: '/fields', icon: Building2 },
            ...(auth.user?.role === 'owner' ? [
                { label: t('employees'), href: '/employees', icon: Users },
                { label: t('reports'), href: '/reports', icon: BarChart3 },
                { label: t('settings'), href: '/settings/organization', icon: Settings },
            ] : []),
        ];

    return <div className={clsx('app-shell', auth.user?.role === 'employee' && 'employee-shell', auth.user?.role === 'owner' && 'owner-shell')}>
        <aside className={clsx('sidebar', open && 'open')}>
            <div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong><button className="icon-btn mobile-only" onClick={() => setOpen(false)} aria-label={t('close')}><X size={20} /></button></div>
            <nav>{nav.map(({ label, href, icon: Icon }) => <Link key={href} href={href} className={location.pathname.startsWith(href) ? 'active' : ''} onClick={() => setOpen(false)} title={label}><Icon size={19} /><span>{label}</span></Link>)}</nav>
        </aside>
        <div className="workspace">
            <header className="topbar">
                <button className="icon-btn mobile-only" onClick={() => setOpen(true)} aria-label={t('menu')}><Menu size={21} /></button>
                <div className="org-title"><strong>{auth.organization?.name ?? 'PitchFlow'}</strong><span>{title}</span></div>
                <div className="top-actions">
                    <button className="icon-btn desktop-only" title={t('search')} onClick={() => setSearchOpen(true)}><Search size={19} /></button>
                    <div className="notification-anchor desktop-only">
                        <button className="icon-btn notification-button" title={t('notifications')} onClick={() => { setNotificationsOpen(!notificationsOpen); setUserMenuOpen(false); setLanguageOpen(false); }}><Bell size={19} />{notification_unread_count > 0 && <span>{notification_unread_count}</span>}</button>
                        {notificationsOpen && <div className="notification-popover">
                            <div className="popover-heading"><strong>{t('notifications')}</strong>{notification_unread_count > 0 && <span>{notification_unread_count}</span>}</div>
                            {notifications.length
                                ? <div className="notification-list">{notifications.map(notification => <article key={notification.id}>
                                    <span className="notification-icon">{notificationIcon(notification.action)}</span>
                                    <div><strong>{notificationLabel(notification.action)}</strong><small>{notification.user?.name ?? t('system')} · {relativeTime(notification.created_at)}</small></div>
                                </article>)}</div>
                                : <p>{t('noNotificationsYet')}</p>}
                        </div>}
                    </div>
                    <div className="topbar-menu-anchor">
                        <button className="language-selector-trigger" onClick={() => { setLanguageOpen(!languageOpen); setNotificationsOpen(false); setUserMenuOpen(false); }} title={t('language')} aria-expanded={languageOpen} aria-label={t('language')}>
                            <span aria-hidden="true">{activeLanguage.flag}</span><strong>{activeLanguage.short}</strong><ChevronDown size={14} />
                        </button>
                        {languageOpen && <div className="topbar-dropdown language-selector-menu">
                            {languageOptions.map(option => <button key={option.code} className={locale === option.code ? 'active' : ''} onClick={() => switchLocale(option.code)}>
                                <span aria-hidden="true">{option.flag}</span><strong>{option.label}</strong>{locale === option.code && <Check size={15} />}
                            </button>)}
                        </div>}
                    </div>
                    <button className="icon-btn" onClick={() => setDark(!dark)} title={dark ? 'Light mode' : 'Dark mode'}>{dark ? <Sun size={19} /> : <Moon size={19} />}</button>
                    <div className="topbar-menu-anchor">
                        <button className="user-menu" onClick={() => { setUserMenuOpen(!userMenuOpen); setNotificationsOpen(false); setLanguageOpen(false); }} title={auth.user?.name}><span>{auth.user?.name}</span><ChevronDown size={16} /></button>
                        {userMenuOpen && <div className="topbar-dropdown user-dropdown">
                            <div className="user-dropdown-header"><strong>{auth.user?.name}</strong><span>{roleLabel}</span></div>
                            <Link href="/profile" onClick={() => setUserMenuOpen(false)}>{t('profile')}</Link>
                            {settingsHref && <Link href={settingsHref} onClick={() => setUserMenuOpen(false)}>{t('settings')}</Link>}
                            <button className="logout-item" onClick={handleLogout} disabled={loggingOut} aria-busy={loggingOut}><LogOut size={16} />{loggingOut ? t('loggingOut') : t('logout')}</button>
                        </div>}
                    </div>
                </div>
            </header>
            <main>{flash.success && <div className="toast success">{flash.success}</div>}{flash.error && <div className="toast error">{flash.error}</div>}{children}</main>
            <GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} />
        </div>
    </div>;
}
