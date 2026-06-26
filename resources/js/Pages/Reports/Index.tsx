import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../../Layouts/AppLayout';
import { Button, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Reports({ report, filters }: { report: any; filters: { from: string; to: string } }) {
    const t = useTranslation(); const [from, setFrom] = useState(filters.from); const [to, setTo] = useState(filters.to);
    const currency = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' });
    const peaks = Object.entries(report.peak_hours).map(([hour, count]) => ({ hour, count }));
    return <AppLayout title={t('reports')}><Head title={t('reports')} /><div className="page-header"><div><h1>{t('reports')}</h1><p>{t('reportsIntro')}</p></div><form className="filter-bar" onSubmit={e => { e.preventDefault(); router.get('/reports', { from, to }, { preserveState: true }); }}><Input aria-label={t('start')} type="date" value={from} onChange={e => setFrom(e.target.value)} /><Input aria-label={t('end')} type="date" value={to} onChange={e => setTo(e.target.value)} /><Button>{t('apply')}</Button></form></div>
        <section className="metrics-grid"><div className="metric"><span>{t('reservations')}</span><strong>{report.reservation_count}</strong></div><div className="metric"><span>{t('collectedRevenue')}</span><strong>{currency.format(report.collected_revenue)}</strong></div><div className="metric"><span>{t('bookedRevenue')}</span><strong>{currency.format(report.booked_revenue)}</strong></div><div className="metric"><span>{t('occupancy')}</span><strong>{report.occupancy_rate}%</strong></div></section>
        <div className="content-grid"><section className="panel"><h2>{t('peakHours')}</h2><div style={{ height: 300 }}><ResponsiveContainer><BarChart data={peaks}><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="hour" /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="#2563eb" radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div></section><section className="panel"><h2>{t('behaviorSignals')}</h2><div className="metric"><span>{t('walkIns')}</span><strong>{report.walk_ins}</strong></div><div className="metric"><span>{t('noShows')}</span><strong>{report.no_shows}</strong></div><div className="metric"><span>{t('lateCancellations')}</span><strong>{report.late_cancellations}</strong></div><p><strong>{t('mostBookedField')}:</strong> {report.most_booked_field ?? t('noData')}</p></section></div>
    </AppLayout>;
}
