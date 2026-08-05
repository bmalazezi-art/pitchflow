import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, EmptyState, PageHeader, Pagination, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

interface SupportRow {
    id: number;
    message: string;
    status: 'open' | 'in_progress' | 'solved';
    created_at: string;
    organization?: { id: number; name: string } | null;
    user?: { id: number; name: string; email?: string | null; phone?: string | null } | null;
}

export default function SupportRequests({ requests, filters }: { requests: Paginated<SupportRow>; filters: { status: string } }) {
    const t = useTranslation();
    const updateStatus = (request: SupportRow, status: string) => router.patch(`/admin/support-requests/${request.id}`, { status }, { preserveScroll: true });

    return <AppLayout title={t('supportRequests')}><Head title={t('supportRequests')} /><div className="owner-page admin-organizations-page">
        <PageHeader eyebrow={t('superAdmin')} title={t('supportRequests')} description={t('supportRequestsIntro')} />
        <section className="admin-filter-bar compact-admin-filter"><Select value={filters.status ?? ''} onChange={event => router.get('/admin/support-requests', { status: event.target.value }, { preserveState: true, replace: true })}><option value="">{t('all')}</option><option value="open">{t('open')}</option><option value="in_progress">{t('inProgress')}</option><option value="solved">{t('solved')}</option></Select></section>
        {requests.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} /></section> : <div className="table-wrap modern-table"><table><thead><tr><th>{t('organization')}</th><th>{t('owner')}</th><th>{t('message')}</th><th>{t('status')}</th><th>{t('timestamp')}</th><th>{t('actions')}</th></tr></thead><tbody>{requests.data.map(item => <tr key={item.id}><td data-label={t('organization')}><strong>{item.organization?.name ?? t('noData')}</strong></td><td data-label={t('owner')}><div className="admin-contact-cell"><strong>{item.user?.name ?? t('owner')}</strong><span>{item.user?.email ?? item.user?.phone ?? t('noData')}</span></div></td><td data-label={t('message')}>{item.message}</td><td data-label={t('status')}><Badge value={item.status} /></td><td data-label={t('timestamp')}>{new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23' }).format(new Date(item.created_at))}</td><td data-label={t('actions')}><div className="row-action-group"><Button variant="secondary" onClick={() => updateStatus(item, 'in_progress')}>{t('inProgress')}</Button><Button variant="secondary" onClick={() => updateStatus(item, 'solved')}>{t('solved')}</Button></div></td></tr>)}</tbody></table></div>}
        {requests.last_page > 1 && <Pagination links={requests.links} />}
    </div></AppLayout>;
}
