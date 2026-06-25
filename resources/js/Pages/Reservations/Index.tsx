import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, Input, Pagination } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

export default function Reservations({ reservations, filters }: { reservations: Paginated<any>; filters: { search?: string } }) {
    const t = useTranslation(); const [search, setSearch] = useState(filters.search ?? '');
    return <AppLayout title={t('reservations')}><Head title={t('reservations')} /><div className="page-header"><div><h1>{t('reservations')}</h1><p>Search and review the complete reservation history.</p></div></div>
        <form className="filter-bar" onSubmit={e => { e.preventDefault(); router.get('/reservations', { search }, { preserveState: true }); }}><Input placeholder={`${t('search')}…`} value={search} onChange={e => setSearch(e.target.value)} /></form>
        {reservations.data.length === 0 ? <div className="panel"><EmptyState title={t('noResults')} /></div> : <><div className="table-wrap"><table><thead><tr><th>Customer</th><th>{t('fields')}</th><th>{t('start')}</th><th>{t('status')}</th><th>{t('payment')}</th></tr></thead><tbody>{reservations.data.map(r => <tr key={r.id}><td><strong>{r.customer_name}</strong><br /><small>{r.customer_phone}</small></td><td>{r.football_field.name}</td><td>{new Date(r.starts_at).toLocaleString()}</td><td><Badge value={r.status} /></td><td><Badge value={r.payment_status} /></td></tr>)}</tbody></table></div><Pagination links={reservations.links} /></>}
    </AppLayout>;
}
