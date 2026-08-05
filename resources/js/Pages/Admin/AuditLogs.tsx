import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, PageHeader, Pagination, SearchInput, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

interface AuditLog {
    id: number;
    action: string;
    description?: string | null;
    properties?: Record<string, unknown> | null;
    created_at: string;
    user?: { id: number; name: string; role: string } | null;
    organization?: { id: number; name: string } | null;
    organization_id?: number | null;
}

export default function AuditLogs({ logs, filters, actions }: { logs: Paginated<AuditLog>; filters: { search: string; action: string }; actions: string[] }) {
    const t = useTranslation();
    const update = (next: Partial<typeof filters>) => router.get('/admin/audit-logs', { ...filters, ...next }, { preserveState: true, replace: true });
    const formatDate = (date: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23' }).format(new Date(date));

    return <AppLayout title={t('auditLogs')}><Head title={t('auditLogs')} /><div className="owner-page admin-organizations-page">
        <PageHeader eyebrow={t('superAdmin')} title={t('auditLogs')} description={t('auditLogsIntro')} />
        <section className="admin-filter-bar compact-admin-filter"><label className="admin-business-search"><Search size={17} /><SearchInput value={filters.search ?? ''} onChange={event => update({ search: event.target.value })} placeholder={t('search')} /></label><Select value={filters.action ?? ''} onChange={event => update({ action: event.target.value })}><option value="">{t('all')}</option>{actions.map(action => <option key={action} value={action}>{action.replaceAll('_', ' ')}</option>)}</Select></section>
        {logs.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} /></section> : <div className="table-wrap modern-table"><table><thead><tr><th>{t('actor')}</th><th>{t('role')}</th><th>{t('organization')}</th><th>{t('action')}</th><th>{t('details')}</th><th>{t('timestamp')}</th></tr></thead><tbody>{logs.data.map(log => <tr key={log.id}><td data-label={t('actor')}>{log.user?.name ?? t('system')}</td><td data-label={t('role')}>{log.user?.role ? <Badge value={log.user.role} /> : t('system')}</td><td data-label={t('organization')}>{log.organization?.name ?? log.organization_id ?? t('noData')}</td><td data-label={t('action')}><strong>{log.action.replaceAll('_', ' ')}</strong></td><td data-label={t('details')}><small>{log.description ?? JSON.stringify(log.properties ?? {})}</small></td><td data-label={t('timestamp')}>{formatDate(log.created_at)}</td></tr>)}</tbody></table></div>}
        {logs.last_page > 1 && <Pagination links={logs.links} />}
    </div></AppLayout>;
}
