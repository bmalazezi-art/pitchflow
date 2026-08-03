import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Building2, MousePointerClick, Phone, Search, TrendingUp, Users } from 'lucide-react';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '../../Layouts/AppLayout';
import { DateRangePeriodPicker } from '../../Components/DateControls';
import { EmptyState, PageHeader } from '../../Components/UI';
import type { RangePeriod } from '../../lib/dateControls';
import { useTranslation } from '../../lib/i18n';

interface AnalyticsReport {
    kpis: {
        total_visits: number;
        unique_visitors: number;
        availability_searches: number;
        call_clicks: number;
        register_business_clicks: number;
        business_views: number;
    };
    conversions: {
        search_conversion: number;
        call_conversion: number;
        registration_interest: number;
    };
    visits_over_time: Array<{ date: string; count: number }>;
    searches_over_time: Array<{ date: string; count: number }>;
    call_clicks_over_time: Array<{ date: string; count: number }>;
    most_searched_cities: Array<{ city_id: number; city_name: string; search_count: number }>;
    most_viewed_businesses: Array<{ organization_id: number; business_name: string; city_name: string | null; views: number; call_clicks: number }>;
    most_clicked_fields: Array<{ football_field_id: number; field_name: string; business_name: string; views: number; call_clicks: number }>;
}

export default function PlatformAnalytics({ analytics, filters }: { analytics: AnalyticsReport; filters: { from: string; to: string; period: RangePeriod } }) {
    const t = useTranslation();
    const number = new Intl.NumberFormat();
    const goToRange = ({ period, from, to }: { period: RangePeriod; from?: string; to?: string }) => {
        const payload = from && to ? { period, from, to } : { period };
        router.get('/admin/analytics', payload, { preserveState: true, preserveScroll: true });
    };
    const kpis = [
        { label: t('totalVisits'), value: analytics.kpis.total_visits, icon: TrendingUp },
        { label: t('uniqueVisitors'), value: analytics.kpis.unique_visitors, icon: Users },
        { label: t('availabilitySearches'), value: analytics.kpis.availability_searches, icon: Search },
        { label: t('callClicks'), value: analytics.kpis.call_clicks, icon: Phone },
        { label: t('registerBusinessClicks'), value: analytics.kpis.register_business_clicks, icon: MousePointerClick },
        { label: t('businessPageViews'), value: analytics.kpis.business_views, icon: Building2 },
    ];

    return <AppLayout title={t('platformAnalytics')}><Head title={t('platformAnalytics')} /><div className="owner-page reports-page platform-analytics-page">
        <PageHeader eyebrow={t('superAdmin')} title={t('platformAnalytics')} description={t('platformAnalyticsIntro')} />
        <DateRangePeriodPicker period={filters.period ?? 'this_month'} from={filters.from} to={filters.to} onApply={goToRange} />

        <section className="report-kpi-grid platform-kpi-grid">{kpis.map(item => { const Icon = item.icon; return <article key={item.label}><span><Icon size={17} /></span><div><small>{item.label}</small><strong>{number.format(item.value)}</strong></div></article>; })}</section>

        <section className="platform-conversion-grid">
            <article><span>{t('searchConversion')}</span><strong>{analytics.conversions.search_conversion}%</strong></article>
            <article><span>{t('callConversion')}</span><strong>{analytics.conversions.call_conversion}%</strong></article>
            <article><span>{t('registrationInterest')}</span><strong>{analytics.conversions.registration_interest}%</strong></article>
        </section>

        <div className="platform-chart-grid">
            <AnalyticsChart title={t('visitsOverTime')} data={analytics.visits_over_time} color="#15803d" />
            <AnalyticsChart title={t('searchesOverTime')} data={analytics.searches_over_time} color="#2563eb" />
            <AnalyticsChart title={t('callClicksOverTime')} data={analytics.call_clicks_over_time} color="#0f766e" />
        </div>

        <div className="platform-table-grid">
            <AnalyticsTable title={t('mostSearchedCities')} empty={analytics.most_searched_cities.length === 0}>
                <table><thead><tr><th>{t('city')}</th><th>{t('searchCount')}</th></tr></thead><tbody>{analytics.most_searched_cities.map(row => <tr key={row.city_id}><td>{row.city_name}</td><td><strong>{number.format(row.search_count)}</strong></td></tr>)}</tbody></table>
            </AnalyticsTable>
            <AnalyticsTable title={t('mostViewedBusinesses')} empty={analytics.most_viewed_businesses.length === 0}>
                <table><thead><tr><th>{t('business')}</th><th>{t('city')}</th><th>{t('views')}</th><th>{t('callClicks')}</th></tr></thead><tbody>{analytics.most_viewed_businesses.map(row => <tr key={row.organization_id}><td>{row.business_name}</td><td>{row.city_name ?? t('noData')}</td><td><strong>{number.format(row.views)}</strong></td><td>{number.format(row.call_clicks)}</td></tr>)}</tbody></table>
            </AnalyticsTable>
            <AnalyticsTable title={t('mostClickedFields')} empty={analytics.most_clicked_fields.length === 0}>
                <table><thead><tr><th>{t('field')}</th><th>{t('business')}</th><th>{t('views')}</th><th>{t('callClicks')}</th></tr></thead><tbody>{analytics.most_clicked_fields.map(row => <tr key={row.football_field_id}><td>{row.field_name}</td><td>{row.business_name}</td><td><strong>{number.format(row.views)}</strong></td><td>{number.format(row.call_clicks)}</td></tr>)}</tbody></table>
            </AnalyticsTable>
        </div>
    </div></AppLayout>;
}

function AnalyticsChart({ title, data, color }: { title: string; data: Array<{ date: string; count: number }>; color: string }) {
    const t = useTranslation();
    return <section className="dashboard-panel"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('analytics')}</span><h2>{title}</h2></div></div><div className="report-chart"><ResponsiveContainer width="100%" height="100%"><AreaChart data={data} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}><defs><linearGradient id={`fill-${title}`} x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor={color} stopOpacity={0.28} /><stop offset="100%" stopColor={color} stopOpacity={0.02} /></linearGradient></defs><CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} /><XAxis dataKey="date" axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: 'var(--muted)', fontSize: 12 }} /><Tooltip contentStyle={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--surface)', color: 'var(--text)' }} /><Area type="monotone" dataKey="count" stroke={color} fill={`url(#fill-${title})`} strokeWidth={2} name={title} /></AreaChart></ResponsiveContainer></div></section>;
}

function AnalyticsTable({ title, empty, children }: { title: string; empty: boolean; children: ReactNode }) {
    const t = useTranslation();
    return <section className="dashboard-panel platform-analytics-table"><div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('analytics')}</span><h2>{title}</h2></div></div>{empty ? <EmptyState title={t('noResults')} /> : <div className="table-wrap modern-table">{children}</div>}</section>;
}
