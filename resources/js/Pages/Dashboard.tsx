import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    Banknote,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    Gauge,
    ShieldCheck,
    Trophy,
    TriangleAlert,
    UserRound,
    WalletCards,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../Layouts/AppLayout';
import { Badge } from '../Components/UI';
import { Button, Field, Modal } from '../Components/UI';
import { useLocale, useTranslation } from '../lib/i18n';
import { useTodayDate } from '../hooks/useTodayDate';
import type { SharedProps } from '../types';

interface DashboardReservation {
    id: number;
    customer_name: string;
    starts_at: string;
    ends_at: string;
    status: string;
    payment_status: string;
    football_field: { id: number; name: string };
}

interface DashboardActivity {
    id: number;
    action: string;
    created_at: string;
    user?: { id: number; name: string } | null;
}

interface DashboardMetrics {
    timezone: string;
    currency: string;
    today_date: string;
    today_reservations: number;
    expected_revenue_today: number;
    today_revenue: number;
    occupancy_rate: number;
    unpaid_reservations: number;
    cancellations_and_no_shows: number;
    busiest_field_today: string | null;
    today_timeline: DashboardReservation[];
    upcoming: DashboardReservation[];
    weekly: Array<{ date: string; count: number }>;
    peak_hours: Record<string, number>;
    recent_activity: DashboardActivity[];
    readiness: {
        complete_count: number;
        total_count: number;
        items: Array<{ key: 'businessProfile' | 'activeFields' | 'employeesReady' | 'publicVisibilityReady'; complete: boolean; href: string }>;
        warnings: string[];
    };
}

interface KpiCardProps {
    label: string;
    value: string | number;
    detail: string;
    icon: LucideIcon;
    tone: 'blue' | 'green' | 'yellow' | 'red';
}

function KpiCard({ label, value, detail, icon: Icon, tone }: KpiCardProps) {
    return <article className={`dashboard-kpi tone-${tone}`}>
        <div className="dashboard-kpi-top"><span>{label}</span><span className="dashboard-kpi-icon"><Icon size={17} /></span></div>
        <strong>{value}</strong>
        <small>{detail}</small>
    </article>;
}

function ReadinessPanel({ readiness }: { readiness: DashboardMetrics['readiness'] }) {
    const t = useTranslation();
    const complete = readiness.complete_count === readiness.total_count;

    return <section className={`dashboard-panel dashboard-readiness-panel ${complete ? 'complete' : ''}`}>
        <div className="dashboard-section-heading">
            <div><span className="dashboard-eyebrow">{t('mvpReadiness')}</span><h2>{complete ? t('readyForBeta') : t('finishSetup')}</h2></div>
            <strong>{readiness.complete_count}/{readiness.total_count}</strong>
        </div>
        <div className="readiness-list">
            {readiness.items.map(item => {
                const Icon = item.complete ? CheckCircle2 : TriangleAlert;
                return <Link key={item.key} href={item.href} className={item.complete ? 'complete' : 'warning'}>
                    <span><Icon size={17} /></span>
                    <div><strong>{t(item.key)}</strong><small>{item.complete ? t('ready') : t(`${item.key}Warning`)}</small></div>
                    <ArrowUpRight size={15} />
                </Link>;
            })}
        </div>
    </section>;
}

export default function Dashboard({ metrics }: { metrics: DashboardMetrics }) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const locale = useLocale();
    const currentLocalDate = useTodayDate();
    const localeCode = locale === 'sq' ? 'sq-AL' : 'en-GB';
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const organizationName = auth.organization?.name ?? 'PitchFlow';
    const currency = new Intl.NumberFormat(localeCode, { style: 'currency', currency: metrics.currency });
    const localHour = Number(new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit', hour12: false, timeZone: metrics.timezone,
    }).format(new Date()));
    const greeting = localHour < 12 ? t('goodMorning') : localHour < 18 ? t('goodAfternoon') : t('goodEvening');
    const todayLabel = new Intl.DateTimeFormat(localeCode, {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }).format(new Date(`${metrics.today_date}T12:00:00`));
    const peakEntries = Object.entries(metrics.peak_hours);
    const [supportOpen, setSupportOpen] = useState(false);
    const supportForm = useForm({ message: '' });
    const peakMaximum = Math.max(...peakEntries.map(([, count]) => count), 1);

    useEffect(() => {
        if (currentLocalDate !== metrics.today_date) {
            router.reload({ preserveScroll: true });
        }
    }, [currentLocalDate, metrics.today_date]);

    const formatTime = (date: string) => new Intl.DateTimeFormat(localeCode, {
        hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone,
    }).format(new Date(date));
    const formatReservationDate = (date: string) => new Intl.DateTimeFormat(localeCode, {
        weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: metrics.timezone,
    }).format(new Date(date));
    const relativeTime = (date: string) => {
        const seconds = Math.max(0, Math.round((Date.now() - new Date(date).getTime()) / 1000));
        if (seconds < 60) return t('justNow');
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return minutes === 1 ? t('oneMinuteAgo') : `${minutes} ${t('minutesAgo')}`;
        const hours = Math.round(minutes / 60);
        if (hours < 24) return hours === 1 ? t('oneHourAgo') : `${hours} ${t('hoursAgo')}`;
        const days = Math.round(hours / 24);
        return days === 1 ? t('yesterday') : `${days} ${t('daysAgo')}`;
    };
    const activityLabel = (action: string) => {
        const key = ({
            login: 'activityLogin', logout: 'activityLogout',
            reservation_created: 'activityReservationCreated', reservation_updated: 'activityReservationUpdated',
            reservation_cancelled: 'activityReservationCancelled', customer_updated: 'activityCustomerUpdated',
            customer_note_created: 'activityCustomerNoteCreated', employee_created: 'activityEmployeeCreated',
            employee_updated: 'activityEmployeeUpdated', employee_deleted: 'activityEmployeeDeleted',
            field_created: 'activityFieldCreated', field_updated: 'activityFieldUpdated',
            field_deleted: 'activityFieldDeleted', settings_updated: 'activitySettingsUpdated',
            organization_registered: 'activityOrganizationRegistered', organization_approved: 'activityOrganizationApproved',
            organization_rejected: 'activityOrganizationRejected', organization_suspended: 'activityOrganizationSuspended',
            city_created: 'activityCityCreated', city_updated: 'activityCityUpdated',
        } as Record<string, Parameters<typeof t>[0]>)[action];

        return key ? t(key) : action.replaceAll('_', ' ');
    };

    return <AppLayout title={t('dashboard')}><Head title={t('dashboard')} />
        <div className="dashboard-page">
            <header className="dashboard-welcome">
                <div>
                    <p className="dashboard-date">{todayLabel}</p>
                    <h1>{greeting}, {firstName} <span aria-hidden="true">👋</span></h1>
                    <p>{t('dashboardTodayAt')} <strong>{organizationName}</strong>.</p>
                </div>
                <div className="dashboard-action-stack"><button className="btn btn-secondary dashboard-primary-action" type="button" onClick={() => setSupportOpen(true)}>{t('needHelp')}</button><Link className="btn btn-primary dashboard-primary-action" href="/calendar"><CalendarDays size={18} />{t('viewCalendar')}</Link></div>
            </header>

            <section className="dashboard-kpi-grid" aria-label={t('todayOverview')}>
                <KpiCard label={t('todayReservations')} value={metrics.today_reservations} detail={metrics.busiest_field_today ? `${t('busiestToday')}: ${metrics.busiest_field_today}` : t('noData')} icon={CalendarCheck} tone="blue" />
                <KpiCard label={t('expectedRevenueToday')} value={currency.format(metrics.expected_revenue_today)} detail={t('fromActiveBookings')} icon={Banknote} tone="green" />
                <KpiCard label={t('paidToday')} value={currency.format(metrics.today_revenue)} detail={t('collectedSoFar')} icon={CircleDollarSign} tone="green" />
                <KpiCard label={t('occupancy')} value={`${metrics.occupancy_rate}%`} detail={t('todayCapacity')} icon={Gauge} tone="blue" />
                <KpiCard label={t('unpaidBookings')} value={metrics.unpaid_reservations} detail={t('needsPayment')} icon={WalletCards} tone="yellow" />
                <KpiCard label={t('cancellationsNoShowsToday')} value={metrics.cancellations_and_no_shows} detail={t('needsAttention')} icon={TriangleAlert} tone="red" />
            </section>

            <ReadinessPanel readiness={metrics.readiness} />

            <div className="dashboard-primary-grid">
                <section className="dashboard-panel dashboard-today-panel">
                    <div className="dashboard-section-heading">
                        <div><span className="dashboard-eyebrow">{t('today')}</span><h2>{t('todayTimeline')}</h2></div>
                        <Link href="/calendar">{t('openCalendar')}<ArrowUpRight size={15} /></Link>
                    </div>
                    {metrics.today_timeline.length === 0
                        ? <div className="dashboard-empty"><span><CalendarCheck size={22} /></span><h3>{t('noReservationsToday')}</h3><p>{t('ownerReadOnlyHint')}</p><Link className="btn btn-primary" href="/calendar"><CalendarDays size={17} />{t('viewCalendar')}</Link></div>
                        : <div className="dashboard-timeline">{metrics.today_timeline.map((reservation) => <article className="timeline-row" key={reservation.id}>
                            <time>{formatTime(reservation.starts_at)}</time>
                            <span className="timeline-marker" aria-hidden="true" />
                            <div className="timeline-booking"><strong>{reservation.customer_name}</strong><span>{reservation.football_field.name} · {formatTime(reservation.starts_at)}–{formatTime(reservation.ends_at)}</span></div>
                            <div className="timeline-badges"><Badge value={reservation.payment_status} /><Badge value={reservation.status} /></div>
                        </article>)}</div>}
                </section>

                <section className="dashboard-panel dashboard-upcoming-panel">
                    <div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('nextUp')}</span><h2>{t('upcoming')}</h2></div></div>
                    {metrics.upcoming.length === 0
                        ? <div className="dashboard-empty compact"><span><ShieldCheck size={21} /></span><h3>{t('noUpcoming')}</h3></div>
                        : <div className="upcoming-list">{metrics.upcoming.map((reservation) => <article className="upcoming-item" key={reservation.id}>
                            <div className="upcoming-avatar"><UserRound size={17} /></div>
                            <div><strong>{reservation.customer_name}</strong><span>{reservation.football_field.name}</span><time>{formatReservationDate(reservation.starts_at)}</time></div>
                            <div className="upcoming-badges"><Badge value={reservation.status} /><Badge value={reservation.payment_status} /></div>
                        </article>)}</div>}
                </section>
            </div>

            <div className="dashboard-insights-grid">
                <section className="dashboard-panel dashboard-chart-panel">
                    <div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('thisWeek')}</span><h2>{t('weeklyReservations')}</h2></div></div>
                    <div className="dashboard-chart"><ResponsiveContainer width="100%" height="100%"><BarChart data={metrics.weekly} margin={{ top: 8, right: 6, left: -22, bottom: 0 }}>
                        <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="date" axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} tickFormatter={(value) => new Intl.DateTimeFormat(localeCode, { weekday: 'short' }).format(new Date(`${value}T12:00:00`))} />
                        <YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} />
                        <Tooltip cursor={{ fill: 'var(--surface-2)' }} contentStyle={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--surface)', color: 'var(--text)' }} />
                        <Bar dataKey="count" name={t('reservations')} fill="var(--blue)" radius={[5, 5, 0, 0]} maxBarSize={44} />
                    </BarChart></ResponsiveContainer></div>
                </section>

                <section className="dashboard-panel dashboard-peaks-panel">
                    <div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('demand')}</span><h2>{t('peakHours')}</h2></div></div>
                    {peakEntries.length === 0
                        ? <div className="dashboard-empty compact"><span><Trophy size={21} /></span><h3>{t('noData')}</h3></div>
                        : <><div className="peak-highlight"><Trophy size={17} /><span>{t('mostActiveHour')}</span><strong>{peakEntries[0][0]}</strong></div>
                            <div className="peak-list">{peakEntries.map(([hour, count]) => <div className="peak-row" key={hour}>
                                <span>{hour}</span><div><i style={{ width: `${(count / peakMaximum) * 100}%` }} /></div><strong>{count}</strong>
                            </div>)}</div></>}
                </section>
            </div>

            <section className="dashboard-panel dashboard-activity-panel">
                <div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('workspace')}</span><h2>{t('recentActivity')}</h2></div></div>
                {metrics.recent_activity.length === 0
                    ? <div className="dashboard-empty compact"><span><Activity size={21} /></span><h3>{t('noData')}</h3></div>
                    : <div className="activity-list">{metrics.recent_activity.map((item) => <article className="activity-item" key={item.id}>
                        <span className="activity-icon"><Activity size={16} /></span>
                        <div><strong>{activityLabel(item.action)}</strong><span>{item.user?.name ?? t('system')} · {relativeTime(item.created_at)}</span></div>
                    </article>)}</div>}
            </section>
            <Modal open={supportOpen} title={t('needHelp')} onClose={() => setSupportOpen(false)}><form className="form-grid one-column" onSubmit={event => { event.preventDefault(); supportForm.post('/support-requests', { preserveScroll: true, onSuccess: () => { supportForm.reset(); setSupportOpen(false); } }); }}><Field label={t('message')} error={supportForm.errors.message} required><textarea className="input" value={supportForm.data.message} onChange={event => supportForm.setData('message', event.target.value)} /></Field><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setSupportOpen(false)}>{t('cancel')}</Button><Button disabled={supportForm.processing}>{t('send')}</Button></div></form></Modal>
        </div>
    </AppLayout>;
}
