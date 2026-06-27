import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarPlus, CheckCircle2, CircleDollarSign, Pencil, XCircle } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, PageHeader, Pagination, SearchInput } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

const quickFilters = ['today', 'tomorrow', 'week', 'pending', 'confirmed', 'completed', 'cancelled', 'paid', 'unpaid'] as const;

export default function Reservations({ reservations, filters, timezone }: { reservations: Paginated<any>; filters: { search?: string; filter?: string }; timezone: string }) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const canManageReservations = auth.user?.role === 'employee';
    const [search, setSearch] = useState(filters.search ?? '');
    const applyFilters = (filter = filters.filter ?? '') => router.get('/reservations', { search, filter }, { preserveState: true, replace: true });
    const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', year: 'numeric', timeZone: timezone }).format(new Date(value));
    const formatTime = (value: string) => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date(value));

    return <AppLayout title={t('reservations')}><Head title={t('reservations')} /><div className="owner-page">
        <PageHeader eyebrow={t('management')} title={t('reservations')} description={t('reservationsIntro')} actions={canManageReservations ? <Link className="btn btn-primary" href="/calendar"><CalendarPlus size={18} />{t('newReservation')}</Link> : <span className="read-only-indicator">{t('readOnly')}</span>} />
        <section className="data-toolbar"><form onSubmit={event => { event.preventDefault(); applyFilters(); }}><SearchInput aria-label={t('search')} placeholder={`${t('searchReservations')}…`} value={search} onChange={event => setSearch(event.target.value)} /></form><div className="quick-filters"><button className={!filters.filter ? 'active' : ''} onClick={() => applyFilters('')}>{t('all')}</button>{quickFilters.map(filter => <button key={filter} className={filters.filter === filter ? 'active' : ''} onClick={() => applyFilters(filter)}>{t(filter)}</button>)}</div></section>
        {reservations.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} action={canManageReservations ? <Link className="btn btn-primary" href="/calendar"><CalendarPlus size={17} />{t('newReservation')}</Link> : undefined} /></section> : <>
            <div className="table-wrap modern-table"><table><thead><tr><th>{t('reservationNumber')}</th><th>{t('customer')}</th><th>{t('phone')}</th><th>{t('field')}</th><th>{t('date')}</th><th>{t('time')}</th><th>{t('amount')}</th><th>{t('payment')}</th><th>{t('status')}</th>{canManageReservations && <th>{t('actions')}</th>}</tr></thead><tbody>{reservations.data.map(reservation => <tr key={reservation.id}>
                <td data-label={t('reservationNumber')}><strong>#{String(reservation.id).padStart(4, '0')}</strong></td><td data-label={t('customer')}><strong>{reservation.customer_name}</strong></td><td data-label={t('phone')}><a href={`tel:${reservation.customer_phone}`}>{reservation.customer_phone}</a></td><td data-label={t('field')}>{reservation.football_field.name}</td><td data-label={t('date')}>{formatDate(reservation.starts_at)}</td><td data-label={t('time')}>{formatTime(reservation.starts_at)}–{formatTime(reservation.ends_at)}</td><td data-label={t('amount')}><strong>{reservation.currency} {Number(reservation.price).toFixed(2)}</strong></td><td data-label={t('payment')}><Badge value={reservation.payment_status} /></td><td data-label={t('status')}><Badge value={reservation.status} /></td>{canManageReservations && <td data-label={t('actions')}><div className="reservation-actions"><Link className="icon-btn bordered" href={`/calendar?field=${reservation.football_field_id}&reservation=${reservation.id}`} title={t('edit')}><Pencil size={16} /></Link>{reservation.payment_status !== 'paid' && <button className="icon-btn bordered success" onClick={() => router.patch(`/reservations/${reservation.id}/paid`, {}, { preserveScroll: true })} title={t('markPaid')}><CircleDollarSign size={16} /></button>}{reservation.status === 'confirmed' && <button className="icon-btn bordered success" onClick={() => router.patch(`/reservations/${reservation.id}/complete`, {}, { preserveScroll: true })} title={t('markCompleted')}><CheckCircle2 size={16} /></button>}{['pending', 'confirmed'].includes(reservation.status) && <button className="icon-btn bordered danger" onClick={() => confirm(t('cancelReservationConfirm')) && router.delete(`/reservations/${reservation.id}`, { preserveScroll: true })} title={t('cancelReservation')}><XCircle size={16} /></button>}</div></td>}
            </tr>)}</tbody></table></div>{reservations.last_page > 1 && <Pagination links={reservations.links} />}</>}
    </div></AppLayout>;
}
