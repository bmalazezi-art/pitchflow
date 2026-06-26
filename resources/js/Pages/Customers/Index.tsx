import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, Input, Pagination } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

export default function Customers({ customers, filters }: { customers: Paginated<any>; filters: { search?: string } }) {
    const t = useTranslation(); const [search, setSearch] = useState(filters.search ?? '');
    return <AppLayout title={t('customers')}><Head title={t('customers')} /><div className="page-header"><div><h1>{t('customers')}</h1><p>{t('customersIntro')}</p></div></div>
        <form className="filter-bar" onSubmit={e => { e.preventDefault(); router.get('/customers', { search }, { preserveState: true }); }}><Input placeholder={`${t('search')} name or phone…`} value={search} onChange={e => setSearch(e.target.value)} /></form>
        {customers.data.length === 0 ? <div className="panel"><EmptyState title={t('noResults')} /></div> : <><div className="table-wrap"><table><thead><tr><th>{t('name')}</th><th>{t('status')}</th><th>{t('reliabilityScore')}</th><th>{t('total')}</th><th>{t('completed')}</th><th>{t('noShows')}</th><th>{t('lateCancellations')}</th><th /></tr></thead><tbody>{customers.data.map(c => <tr key={c.id}><td><strong>{c.name}</strong><br /><small>{c.phone}</small></td><td><Badge value={c.reliability_status} /></td><td>{c.reliability_score}/100</td><td>{c.total_reservations}</td><td>{c.completed_reservations}</td><td>{c.no_shows}</td><td>{c.late_cancellations}</td><td><Link className="icon-btn" href={`/customers/${c.id}`} aria-label={t('profile')}><ChevronRight size={18} /></Link></td></tr>)}</tbody></table></div><Pagination links={customers.links} /></>}
    </AppLayout>;
}
