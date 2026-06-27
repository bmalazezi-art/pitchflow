import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Bell, Building2, CalendarDays, ChevronLeft, CircleUserRound, LayoutDashboard, LogOut, Menu, Moon, Search, Settings, Sun, Users, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import clsx from 'clsx';
import { useTranslation } from '../lib/i18n';
import type { SharedProps } from '../types';
import GlobalSearch from '../Components/GlobalSearch';

export default function AppLayout({ children, title }: { children: ReactNode; title: string }) {
    const { auth, flash, locale } = usePage<SharedProps>().props;
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [dark, setDark] = useState(() => localStorage.getItem('theme') === 'dark');
    const [searchOpen, setSearchOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    }, [dark]);

    const nav = auth.user?.role === 'super_admin'
        ? [
            { label: t('organizations'), href: '/admin/organizations', icon: Building2 },
            { label: 'Cities', href: '/admin/cities', icon: Settings },
        ]
        : auth.user?.role === 'employee'
            ? [
                { label: t('dashboard'), href: '/dashboard', icon: LayoutDashboard },
                { label: t('calendar'), href: '/calendar', icon: CalendarDays },
                { label: t('reservations'), href: '/reservations', icon: BarChart3 },
                { label: t('customers'), href: '/customers', icon: CircleUserRound },
                { label: t('myAssignedFields'), href: '/fields', icon: Building2 },
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

    return <div className={clsx('app-shell', collapsed && 'sidebar-collapsed', auth.user?.role === 'employee' && 'employee-shell')}>
        <aside className={clsx('sidebar', open && 'open')}>
            <div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong><button className="icon-btn mobile-only" onClick={() => setOpen(false)} aria-label={t('close')}><X size={20} /></button></div>
            <nav>{nav.map(({ label, href, icon: Icon }) => <Link key={href} href={href} className={location.pathname.startsWith(href) ? 'active' : ''} onClick={() => setOpen(false)} title={label}><Icon size={19} /><span>{label}</span></Link>)}</nav>
            <button className="collapse-btn desktop-only" onClick={() => setCollapsed(!collapsed)}><ChevronLeft size={18} /><span>{t('collapse')}</span></button>
        </aside>
        <div className="workspace">
            <header className="topbar">
                <button className="icon-btn mobile-only" onClick={() => setOpen(true)} aria-label={t('menu')}><Menu size={21} /></button>
                <div className="org-title"><strong>{auth.organization?.name ?? 'PitchFlow'}</strong><span>{title}</span></div>
                <div className="top-actions">
                    <button className="icon-btn desktop-only" title={t('search')} onClick={() => setSearchOpen(true)}><Search size={19} /></button>
                    <div className="notification-anchor desktop-only">
                        <button className="icon-btn" title={t('notifications')} onClick={() => setNotificationsOpen(!notificationsOpen)}><Bell size={19} /></button>
                        {notificationsOpen && <div className="notification-popover"><strong>{t('notifications')}</strong><p>{t('noNotifications')}</p></div>}
                    </div>
                    <button className="icon-btn" onClick={() => router.post('/locale', { locale: locale === 'en' ? 'sq' : 'en' }, { preserveScroll: true })} title={t('language')}>{locale.toUpperCase()}</button>
                    <button className="icon-btn" onClick={() => setDark(!dark)} title={dark ? 'Light mode' : 'Dark mode'}>{dark ? <Sun size={19} /> : <Moon size={19} />}</button>
                    <button className="user-menu" onClick={() => router.post('/logout')} title={t('logout')}><span>{auth.user?.name}</span><LogOut size={17} /></button>
                </div>
            </header>
            <main>{flash.success && <div className="toast success">{flash.success}</div>}{flash.error && <div className="toast error">{flash.error}</div>}{children}</main>
            <GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} />
        </div>
    </div>;
}
