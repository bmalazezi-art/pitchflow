import { Head, router } from '@inertiajs/react';
import { Banknote, CalendarCheck, CircleDollarSign, FileSpreadsheet, FileText, Gauge, Trophy, Users } from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../../Layouts/AppLayout';
import { Button, Input, PageHeader } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Reports({ report, filters }: { report: any; filters: { from: string; to: string } }) {
    const t = useTranslation();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const currency = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' });
    const peaks = Object.entries(report.peak_hours).map(([hour, count]) => ({ hour, count }));
    const payments = [{ label: t('paid'), value: report.paid_reservations, tone: 'paid' }, { label: t('partial'), value: report.partial_reservations, tone: 'partial' }, { label: t('unpaid'), value: report.unpaid_reservations, tone: 'unpaid' }];
    const escapeCell = (value: unknown) => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character] ?? character);
    const exportExcel = () => {
        const rows = [
            [t('reservations'), report.reservation_count], [t('collectedRevenue'), report.collected_revenue],
            [t('bookedRevenue'), report.booked_revenue], [t('occupancy'), `${report.occupancy_rate}%`],
            [t('mostBookedField'), report.most_booked_field ?? t('noData')], [t('paid'), report.paid_reservations],
            [t('partial'), report.partial_reservations], [t('unpaid'), report.unpaid_reservations],
        ];
        const table = `<table>${rows.map(row => `<tr><td>${escapeCell(row[0])}</td><td>${escapeCell(row[1])}</td></tr>`).join('')}</table>`;
        const url = URL.createObjectURL(new Blob([table], { type: 'application/vnd.ms-excel' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `pitchflow-report-${from}-${to}.xls`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return <AppLayout title={t('reports')}><Head title={t('reports')} /><div className="owner-page reports-page">
        <PageHeader eyebrow={t('analytics')} title={t('reports')} description={t('reportsIntro')} actions={<div className="report-actions"><div><Button type="button" variant="secondary" onClick={() => window.print()}><FileText size={16} />{t('exportPdf')}</Button><Button type="button" variant="secondary" onClick={exportExcel}><FileSpreadsheet size={16} />{t('exportExcel')}</Button></div><form className="report-range" onSubmit={event => { event.preventDefault(); router.get('/reports', { from, to }, { preserveState: true }); }}><Input aria-label={t('start')} type="date" value={from} onChange={event => setFrom(event.target.value)} /><span>–</span><Input aria-label={t('end')} type="date" value={to} onChange={event => setTo(event.target.value)} /><Button>{t('apply')}</Button></form></div>} />
        <section className="report-kpi-grid"><article><span><CalendarCheck size={17} /></span><div><small>{t('reservations')}</small><strong>{report.reservation_count}</strong></div></article><article><span className="green"><CircleDollarSign size={17} /></span><div><small>{t('collectedRevenue')}</small><strong>{currency.format(report.collected_revenue)}</strong></div></article><article><span className="green"><Banknote size={17} /></span><div><small>{t('bookedRevenue')}</small><strong>{currency.format(report.booked_revenue)}</strong></div></article><article><span><Gauge size={17} /></span><div><small>{t('occupancy')}</small><strong>{report.occupancy_rate}%</strong></div></article></section>
        <div className="report-main-grid"><section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('demand')}</span><h2>{t('peakHours')}</h2></div></div><div className="report-chart"><ResponsiveContainer width="100%" height="100%"><BarChart data={peaks} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}><CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} /><XAxis dataKey="hour" axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><Tooltip contentStyle={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--surface)', color: 'var(--text)' }} /><Bar dataKey="count" name={t('reservations')} fill="#2563eb" radius={[5, 5, 0, 0]} maxBarSize={54} /></BarChart></ResponsiveContainer></div></section>
            <section className="dashboard-panel report-highlight"><span><Trophy size={22} /></span><small>{t('mostBookedField')}</small><strong>{report.most_booked_field ?? t('noData')}</strong><p>{report.reservation_count} {t('reservations').toLowerCase()}</p></section></div>
        <div className="report-secondary-grid"><section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('revenue')}</span><h2>{t('paymentStatistics')}</h2></div></div><div className="payment-stat-list">{payments.map(item => <div key={item.tone}><span className={item.tone} /><strong>{item.label}</strong><b>{item.value}</b></div>)}</div></section>
            <section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('operations')}</span><h2>{t('behaviorSignals')}</h2></div></div><div className="behavior-grid"><div><Users size={18} /><span>{t('walkIns')}</span><strong>{report.walk_ins}</strong></div><div><span className="signal-dot red" /><span>{t('noShows')}</span><strong>{report.no_shows}</strong></div><div><span className="signal-dot yellow" /><span>{t('lateCancellations')}</span><strong>{report.late_cancellations}</strong></div></div></section></div>
    </div></AppLayout>;
}
