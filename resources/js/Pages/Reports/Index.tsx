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
    return <AppLayout title={t('reports')}><Head title={t('reports')} /><div className="page-header"><div><h1>{t('reports')}</h1><p>Operational performance for the selected period.</p></div><form className="filter-bar" onSubmit={e => { e.preventDefault(); router.get('/reports', { from, to }, { preserveState: true }); }}><Input type="date" value={from} onChange={e => setFrom(e.target.value)} /><Input type="date" value={to} onChange={e => setTo(e.target.value)} /><Button>Apply</Button></form></div>
        <section className="metrics-grid"><div className="metric"><span>Reservations</span><strong>{report.reservation_count}</strong></div><div className="metric"><span>Collected revenue</span><strong>{currency.format(report.collected_revenue)}</strong></div><div className="metric"><span>Booked revenue</span><strong>{currency.format(report.booked_revenue)}</strong></div><div className="metric"><span>{t('occupancy')}</span><strong>{report.occupancy_rate}%</strong></div></section>
        <div className="content-grid"><section className="panel"><h2>Peak booking hours</h2><div style={{ height: 300 }}><ResponsiveContainer><BarChart data={peaks}><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="hour" /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="#2563eb" radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div></section><section className="panel"><h2>Behavior signals</h2><div className="metric"><span>Walk-ins</span><strong>{report.walk_ins}</strong></div><div className="metric"><span>No-shows</span><strong>{report.no_shows}</strong></div><div className="metric"><span>Late cancellations</span><strong>{report.late_cancellations}</strong></div><p><strong>Most booked field:</strong> {report.most_booked_field ?? 'No data'}</p></section></div>
    </AppLayout>;
}
