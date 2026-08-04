import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, CalendarPlus, CheckCircle2, ChevronRight, Clock3, CreditCard, MessageSquareText, Pencil, Search, Trophy, UserPlus, UserRound, XCircle } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import { useTodayDate } from '../../hooks/useTodayDate';
import type { SharedProps } from '../../types';
import { useEffect } from 'react';

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
    today_reservations: OperationalReservation[];
    upcoming: OperationalReservation[];
    recent_activity: Array<{ id: number; action: string; created_at: string }>;
}

export default function EmployeeDashboard({ metrics }: { metrics: EmployeeMetrics }) {
    const t = useTranslation();
    const { auth, locale } = usePage<SharedProps>().props;
    const currentLocalDate = useTodayDate();
    const localeCode = locale === 'sq' ? 'sq-AL' : 'en-GB';
    const formatTime = (value: string) => new Intl.DateTimeFormat(localeCode, { hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone }).format(new Date(value));
    const formatDateTime = (value: string) => new Intl.DateTimeFormat(localeCode, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone }).format(new Date(value));
    const today = new Intl.DateTimeFormat(localeCode, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${metrics.today_date}T12:00:00`));
    useEffect(() => {
        if (currentLocalDate !== metrics.today_date) {
            router.reload({ preserveScroll: true });
        }
    }, [currentLocalDate, metrics.today_date]);
    const activityLabel = (action: string) => ({
        reservation_created: t('activityReservationCreated'), reservation_updated: t('activityReservationUpdated'),
        reservation_cancelled: t('activityReservationCancelled'), reservation_completed: t('reservationCompleted'),
        reservation_marked_paid: t('reservationMarkedPaid'), customer_note_created: t('activityCustomerNoteCreated'),
    } as Record<string, string>)[action] ?? action.replaceAll('_', ' ');
    const activityVisual = (action: string) => ({
        reservation_created: { icon: CalendarPlus, tone: 'created' },
        reservation_updated: { icon: Pencil, tone: 'updated' },
        reservation_cancelled: { icon: XCircle, tone: 'cancelled' },
        reservation_completed: { icon: CheckCircle2, tone: 'completed' },
        reservation_marked_paid: { icon: CreditCard, tone: 'paid' },
        customer_note_created: { icon: MessageSquareText, tone: 'note' },
    } as Record<string, { icon: typeof Clock3; tone: string }>)[action] ?? { icon: Clock3, tone: 'updated' };

    return <AppLayout title={t('operationsDashboard')}><Head title={t('operationsDashboard')} /><div className="operations-page">
        <header className="operations-welcome"><div><span>{today}</span><h1>{t('readyForToday')}, {auth.user?.name.split(' ')[0]}</h1><p>{t('employeeDashboardIntro')}</p></div><Link className="operations-primary" href="/calendar"><CalendarPlus size={19} />{t('newReservation')}</Link></header>
        <section className="operations-stat-grid">
            <article><span className="blue"><CalendarCheck size={20} /></span><div><small>{t('todayReservations')}</small><strong>{metrics.today_reservation_count}</strong></div></article>
            <article><span className="green"><Clock3 size={20} /></span><div><small>{t('availableSlotsToday')}</small><strong>{metrics.available_slots_today}</strong></div></article>
            <article><span className="orange"><ChevronRight size={20} /></span><div><small>{t('nextReservation')}</small><strong>{metrics.next_reservation ? formatTime(metrics.next_reservation.starts_at) : '—'}</strong><em>{metrics.next_reservation?.customer_name ?? t('noUpcoming')}</em></div></article>
            <article><span className="violet"><Trophy size={20} /></span><div><small>{t('activeAssignedFields')}</small><strong>{metrics.active_field_count}</strong></div></article>
        </section>
        <section className="operations-quick-actions"><h2>{t('quickActions')}</h2><div><Link href="/calendar"><CalendarPlus size={21} /><span><strong>{t('newReservation')}</strong><small>{t('bookAvailableSlot')}</small></span></Link><Link href="/calendar"><CalendarDays size={21} /><span><strong>{t('bookingBoard')}</strong><small>{t('viewTodaySchedule')}</small></span></Link><Link href="/customers"><Search size={21} /><span><strong>{t('searchCustomer')}</strong><small>{t('historyAndNotes')}</small></span></Link><Link href="/calendar"><UserPlus size={21} /><span><strong>{t('walkIn')}</strong><small>{t('walkInHelp')}</small></span></Link></div></section>
        <div className="operations-grid">
            <section className="operations-panel"><header><div><span>{t('today')}</span><h2>{t('todayTimeline')}</h2></div><Link href="/calendar">{t('bookingBoard')}<ChevronRight size={15} /></Link></header>{metrics.today_reservations.length === 0 ? <div className="operations-empty"><CalendarDays size={24} /><p>{t('noReservationsToday')}</p><Link className="operations-empty-action" href="/calendar"><CalendarPlus size={16} />{t('newReservation')}</Link></div> : <div className="operations-upcoming">{metrics.today_reservations.map(reservation => <article key={reservation.id}><time>{formatTime(reservation.starts_at)}</time><span className="operations-line" /><div><strong>{reservation.customer_name}</strong><small>{reservation.football_field.name}</small></div><div><Badge value={reservation.payment_status} /><Badge value={reservation.status} /></div></article>)}</div>}</section>
            <section className="operations-panel"><header><div><span>{t('myWork')}</span><h2>{t('recentActivity')}</h2></div></header>{metrics.recent_activity.length === 0 ? <div className="operations-empty"><UserRound size={24} /><p>{t('noData')}</p></div> : <div className="operations-activity">{metrics.recent_activity.map(activity => { const visual = activityVisual(activity.action); const ActivityIcon = visual.icon; return <article key={activity.id}><span className={visual.tone}><ActivityIcon size={15} /></span><div><strong>{activityLabel(activity.action)}</strong><small>{formatDateTime(activity.created_at)}</small></div></article>; })}</div>}</section>
        </div>
    </div></AppLayout>;
}
