import { Head, Link } from '@inertiajs/react';
import { CalendarPlus, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../Layouts/AppLayout';
import { Badge, EmptyState } from '../Components/UI';
import { useTranslation } from '../lib/i18n';

export default function Dashboard({ metrics }: { metrics: any }) {
    const t = useTranslation();
    const currency = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' });
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
        } as Record<string, any>)[action];

        return key ? t(key) : action.replaceAll('_', ' ');
    };
    return <AppLayout title={t('dashboard')}><Head title={t('dashboard')} />
        <div className="page-header"><div><h1>{t('dashboard')}</h1><p>{t('dashboardIntro')}</p></div><Link className="btn btn-primary" href="/calendar"><CalendarPlus size={18} />{t('newReservation')}</Link></div>
        <section className="metrics-grid">
            <div className="metric"><span>{t('todayReservations')}</span><strong>{metrics.today_reservations}</strong></div>
            <div className="metric"><span>{t('todayRevenue')}</span><strong>{currency.format(metrics.today_revenue)}</strong></div>
            <div className="metric"><span>{t('monthlyRevenue')}</span><strong>{currency.format(metrics.monthly_revenue)}</strong></div>
            <div className="metric"><span>{t('occupancy')}</span><strong>{metrics.occupancy_rate}%</strong></div>
        </section>
        <div className="content-grid">
            <section className="panel"><h2>{t('weeklyReservations')}</h2><div style={{ height: 260 }}><ResponsiveContainer width="100%" height="100%"><BarChart data={metrics.weekly}><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="date" tickFormatter={(v) => v.slice(5)} /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="#2563eb" radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div></section>
            <section className="panel"><div className="page-header"><div><h2>{t('upcoming')}</h2></div><span className="badge badge-active"><Users size={13} /> {metrics.active_employees}</span></div>
                {metrics.upcoming.length === 0 ? <EmptyState title={t('noUpcoming')} /> : metrics.upcoming.map((r: any) => <div key={r.id} style={{ padding: '11px 0', borderTop: '1px solid var(--border)' }}><strong>{r.customer_name}</strong><div style={{ color: 'var(--muted)', fontSize: 13 }}>{r.football_field.name} · {new Date(r.starts_at).toLocaleString()}</div><Badge value={r.status} /></div>)}
            </section>
        </div>
        <div className="content-grid" style={{ marginTop: 18 }}>
            <section className="panel">
                <h2>{t('peakHours')}</h2>
                {Object.keys(metrics.peak_hours).length === 0
                    ? <EmptyState title={t('noData')} />
                    : Object.entries(metrics.peak_hours).map(([hour, count]) => <div key={hour} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderTop: '1px solid var(--border)' }}><strong>{hour}</strong><span>{String(count)} {t('reservations').toLowerCase()}</span></div>)}
                <p><strong>{t('mostBookedField')}:</strong> {metrics.most_booked_field ?? t('noData')}</p>
            </section>
            <section className="panel">
                <h2>{t('recentActivity')}</h2>
                {metrics.recent_activity.length === 0
                    ? <EmptyState title={t('noData')} />
                    : metrics.recent_activity.map((activity: any) => <div key={activity.id} style={{ padding: '10px 0', borderTop: '1px solid var(--border)' }}><strong>{activityLabel(activity.action)}</strong><small style={{ display: 'block', color: 'var(--muted)' }}>{activity.user?.name ?? t('system')} · {new Date(activity.created_at).toLocaleString()}</small></div>)}
            </section>
        </div>
    </AppLayout>;
}
