import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, CalendarPlus, ChevronRight, Clock3, Search, Trophy, UserRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

interface OperationalReservation {
    id: number;
    customer_name: string;
    customer_phone: string;
    starts_at: string;
    ends_at: string;
    status: string;
    payment_status: string;
    football_field: { id: number; name: string };
}

interface EmployeeMetrics {
    timezone: string;
    today_date: string;
    today_reservation_count: number;
    available_slots_today: number;
    active_field_count: number;
    next_reservation: OperationalReservation | null;
    upcoming: OperationalReservation[];
    recent_activity: Array<{ id: number; action: string; created_at: string }>;
}

export default function EmployeeDashboard({ metrics }: { metrics: EmployeeMetrics }) {
    const t = useTranslation();
    const { auth, locale } = usePage<SharedProps>().props;
    const localeCode = locale === 'sq' ? 'sq-AL' : 'en-GB';
    const formatTime = (value: string) => new Intl.DateTimeFormat(localeCode, { hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone }).format(new Date(value));
    const formatDateTime = (value: string) => new Intl.DateTimeFormat(localeCode, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone }).format(new Date(value));
    const today = new Intl.DateTimeFormat(localeCode, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${metrics.today_date}T12:00:00Z`));
    const activityLabel = (action: string) => ({
        reservation_created: t('activityReservationCreated'), reservation_updated: t('activityReservationUpdated'),
        reservation_cancelled: t('activityReservationCancelled'), reservation_completed: t('reservationCompleted'),
        reservation_marked_paid: t('reservationMarkedPaid'), customer_note_created: t('activityCustomerNoteCreated'),
    } as Record<string, string>)[action] ?? action.replaceAll('_', ' ');

    return <AppLayout title={t('operationsDashboard')}><Head title={t('operationsDashboard')} /><div className="operations-page">
        <header className="operations-welcome"><div><span>{today}</span><h1>{t('readyForToday')}, {auth.user?.name.split(' ')[0]}</h1><p>{t('employeeDashboardIntro')}</p></div><Link className="operations-primary" href="/calendar"><CalendarPlus size={19} />{t('newReservation')}</Link></header>
        <section className="operations-stat-grid">
            <article><span className="blue"><CalendarCheck size={20} /></span><div><small>{t('todayReservations')}</small><strong>{metrics.today_reservation_count}</strong></div></article>
            <article><span className="green"><Clock3 size={20} /></span><div><small>{t('availableSlotsToday')}</small><strong>{metrics.available_slots_today}</strong></div></article>
            <article><span className="orange"><ChevronRight size={20} /></span><div><small>{t('nextReservation')}</small><strong>{metrics.next_reservation ? formatTime(metrics.next_reservation.starts_at) : '—'}</strong><em>{metrics.next_reservation?.customer_name ?? t('noUpcoming')}</em></div></article>
            <article><span className="violet"><Trophy size={20} /></span><div><small>{t('activeAssignedFields')}</small><strong>{metrics.active_field_count}</strong></div></article>
        </section>
        <section className="operations-quick-actions"><h2>{t('quickActions')}</h2><div><Link href="/calendar"><CalendarPlus size={21} /><span><strong>{t('newReservation')}</strong><small>{t('bookAvailableSlot')}</small></span></Link><Link href="/calendar"><CalendarDays size={21} /><span><strong>{t('openCalendar')}</strong><small>{t('viewTodaySchedule')}</small></span></Link><Link href="/customers"><Search size={21} /><span><strong>{t('searchCustomer')}</strong><small>{t('historyAndNotes')}</small></span></Link></div></section>
        <div className="operations-grid">
            <section className="operations-panel"><header><div><span>{t('nextUp')}</span><h2>{t('upcoming')}</h2></div><Link href="/reservations">{t('viewAll')}<ChevronRight size={15} /></Link></header>{metrics.upcoming.length === 0 ? <div className="operations-empty"><CalendarDays size={24} /><p>{t('noUpcoming')}</p></div> : <div className="operations-upcoming">{metrics.upcoming.map(reservation => <article key={reservation.id}><time>{formatTime(reservation.starts_at)}</time><span className="operations-line" /><div><strong>{reservation.customer_name}</strong><small>{reservation.football_field.name} · {formatDateTime(reservation.starts_at)}</small></div><div><Badge value={reservation.payment_status} /><Badge value={reservation.status} /></div></article>)}</div>}</section>
            <section className="operations-panel"><header><div><span>{t('myWork')}</span><h2>{t('recentActivity')}</h2></div></header>{metrics.recent_activity.length === 0 ? <div className="operations-empty"><UserRound size={24} /><p>{t('noData')}</p></div> : <div className="operations-activity">{metrics.recent_activity.map(activity => <article key={activity.id}><span><Clock3 size={15} /></span><div><strong>{activityLabel(activity.action)}</strong><small>{formatDateTime(activity.created_at)}</small></div></article>)}</div>}</section>
        </div>
    </div></AppLayout>;
}
