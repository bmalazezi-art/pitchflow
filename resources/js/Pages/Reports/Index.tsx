import { Head, router } from '@inertiajs/react';
import { Banknote, CalendarCheck, CircleDollarSign, FileSpreadsheet, FileText, Gauge, Trophy, Users, XCircle } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../../Layouts/AppLayout';
import { Button, PageHeader } from '../../Components/UI';
import { DateRangePeriodPicker } from '../../Components/DateControls';
import type { RangePeriod } from '../../lib/dateControls';
import { useTranslation } from '../../lib/i18n';

export default function Reports({ report, filters }: { report: any; filters: { from: string; to: string; period: RangePeriod } }) {
    const t = useTranslation();
    const currency = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' });
    const peaks = Object.entries(report.peak_hours).map(([hour, count]) => ({ hour, count }));
    const payments = [
        { label: t('paid'), value: report.paid_reservations, total: report.payment_stats?.paid?.paid_total ?? 0, tone: 'paid' },
        { label: t('partial'), value: report.partial_reservations, total: report.payment_stats?.partial?.paid_total ?? 0, tone: 'partial' },
        { label: t('unpaid'), value: report.unpaid_reservations, total: report.payment_stats?.unpaid?.booked_total ?? 0, tone: 'unpaid' },
    ];
    const cancellationReasonLabels: Record<string, string> = {
        customer_called: t('customerCalledToCancel'),
        customer_no_show: t('customerNoShow'),
        weather_issue: t('weatherTechnicalIssue'),
        field_unavailable: t('fieldUnavailableReason'),
        duplicate_wrong_booking: t('duplicateWrongBooking'),
        correction_cancel: t('cancelReservation'),
        correction_no_show: t('noShow'),
        correction_void: t('voidReservation'),
        unknown: t('noData'),
    };
    const cancellationReasons = Object.entries(report.cancellations_by_reason ?? {});
    const cancellationEmployees = Object.entries(report.cancellations_by_employee ?? {});
    const goToRange = ({ period, from, to }: { period: RangePeriod; from?: string; to?: string }) => {
        const payload = from && to ? { period, from, to } : { period };
        router.get('/reports', payload, { preserveState: true, preserveScroll: true });
    };
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
        link.download = `pitchflow-report-${filters.from}-${filters.to}.xls`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return <AppLayout title={t('reports')}><Head title={t('reports')} /><div className="owner-page reports-page">
        <PageHeader eyebrow={t('analytics')} title={t('reports')} description={t('reportsIntro')} actions={<div className="report-actions"><div><Button type="button" variant="secondary" onClick={() => window.print()}><FileText size={16} />{t('exportPdf')}</Button><Button type="button" variant="secondary" onClick={exportExcel}><FileSpreadsheet size={16} />{t('exportExcel')}</Button></div></div>} />
        <DateRangePeriodPicker period={filters.period ?? 'this_month'} from={filters.from} to={filters.to} onApply={goToRange} />
        {report.revenue_warning && <div className="report-warning">{t('reservationsFoundNoPrice')}</div>}
        <section className="report-kpi-grid"><article><span><CalendarCheck size={17} /></span><div><small>{t('reservations')}</small><strong>{report.reservation_count}</strong></div></article><article><span className="green"><CircleDollarSign size={17} /></span><div><small>{t('collectedRevenue')}</small><strong>{currency.format(report.collected_revenue)}</strong></div></article><article><span className="green"><Banknote size={17} /></span><div><small>{t('bookedRevenue')}</small><strong>{currency.format(report.booked_revenue)}</strong></div></article><article><span><Gauge size={17} /></span><div><small>{t('occupancy')}</small><strong>{report.occupancy_rate}%</strong></div></article><article><span className="danger"><XCircle size={17} /></span><div><small>{t('totalCancellations')}</small><strong>{report.total_cancellations ?? 0}</strong></div></article></section>
        <div className="report-main-grid"><section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('demand')}</span><h2>{t('peakHours')}</h2></div></div><div className="report-chart"><ResponsiveContainer width="100%" height="100%"><BarChart data={peaks} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}><CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} /><XAxis dataKey="hour" axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><Tooltip contentStyle={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--surface)', color: 'var(--text)' }} /><Bar dataKey="count" name={t('reservations')} fill="var(--blue)" radius={[5, 5, 0, 0]} maxBarSize={54} /></BarChart></ResponsiveContainer></div></section>
            <section className="dashboard-panel report-highlight"><span><Trophy size={22} /></span><small>{t('mostBookedField')}</small><strong>{report.most_booked_field ?? t('noData')}</strong><p>{report.reservation_count} {t('reservations').toLowerCase()}</p></section></div>
        <div className="report-secondary-grid"><section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('revenue')}</span><h2>{t('paymentStatistics')}</h2></div></div><div className="payment-stat-list">{payments.map(item => <div key={item.tone}><span className={item.tone} /><strong>{item.label}<small>{currency.format(item.total)}</small></strong><b>{item.value}</b></div>)}</div></section>
            <section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('operations')}</span><h2>{t('behaviorSignals')}</h2></div></div><div className="behavior-grid"><div><Users size={18} /><span>{t('walkIns')}</span><strong>{report.walk_ins}</strong></div><div><span className="signal-dot red" /><span>{t('noShows')}</span><strong>{report.no_shows}</strong></div><div><span className="signal-dot yellow" /><span>{t('lateCancellations')}</span><strong>{report.late_cancellations}</strong></div><div><XCircle size={18} /><span>{t('paidCancelledRefundNeeded')}</span><strong>{currency.format(report.paid_cancelled_revenue ?? 0)}</strong></div><div><span className="signal-dot yellow" /><span>{t('correctionRequests')}</span><strong>{report.correction_requests ?? 0}</strong></div><div><span className="signal-dot red" /><span>{t('correctedReservations')}</span><strong>{report.corrected_reservations ?? 0}</strong></div><div><span className="signal-dot yellow" /><span>{t('waitingListRequests')}</span><strong>{report.waiting_list_requests ?? 0}</strong></div><div><span className="signal-dot green" /><span>{t('notifiedWaitingListRequests')}</span><strong>{report.notified_waiting_list_requests ?? 0}</strong></div></div></section></div>
        <div className="report-secondary-grid"><section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('totalCancellations')}</span><h2>{t('cancellationsByReason')}</h2></div></div><div className="payment-stat-list">{cancellationReasons.length ? cancellationReasons.map(([reason, count]) => <div key={reason}><span className="unpaid" /><strong>{cancellationReasonLabels[reason] ?? reason.replaceAll('_', ' ')}</strong><b>{String(count)}</b></div>) : <p className="muted-copy">{t('noData')}</p>}</div></section>
            <section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('operations')}</span><h2>{t('cancelledByEmployee')}</h2></div></div><div className="payment-stat-list">{cancellationEmployees.length ? cancellationEmployees.map(([employee, count]) => <div key={employee}><span className="partial" /><strong>{employee}</strong><b>{String(count)}</b></div>) : <p className="muted-copy">{t('noData')}</p>}</div></section></div>
    </div></AppLayout>;
}
