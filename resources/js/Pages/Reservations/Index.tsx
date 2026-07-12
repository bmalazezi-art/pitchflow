import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarPlus, CheckCircle2, CircleDollarSign, Clock3, Eye, Pencil, UserRound, WalletCards, XCircle } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Input, PageHeader, Pagination, SearchInput, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

type Filters = {
    search?: string;
    date_filter?: string;
    payment_filter?: string;
    status_filter?: string;
    from?: string;
    to?: string;
};

type Summary = {
    total: number;
    paid: number;
    unpaid: number;
    partial: number;
    pending: number;
    cancelled: number;
    completed: number;
};

export default function Reservations({ reservations, filters, summary, timezone }: { reservations: Paginated<any>; filters: Filters; summary: Summary; timezone: string }) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const canManageReservations = auth.user?.role === 'employee';
    const [search, setSearch] = useState(filters.search ?? '');
    const [dateFilter, setDateFilter] = useState(filters.date_filter ?? 'today');
    const [paymentFilter, setPaymentFilter] = useState(filters.payment_filter ?? 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status_filter ?? 'all');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [selected, setSelected] = useState<any>(null);
    const applyFilters = (overrides: Partial<Filters> = {}) => {
        const nextDateFilter = overrides.date_filter ?? dateFilter;
        router.get('/reservations', {
            search: overrides.search ?? search,
            date_filter: nextDateFilter,
            payment_filter: overrides.payment_filter ?? paymentFilter,
            status_filter: overrides.status_filter ?? statusFilter,
            ...(nextDateFilter === 'custom' ? { from: overrides.from ?? from, to: overrides.to ?? to } : {}),
        }, { preserveState: true, replace: true });
    };
    const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', year: 'numeric', timeZone: timezone }).format(new Date(value));
    const formatTime = (value: string) => new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date(value));
    const periodLabel = dateFilter === 'tomorrow' ? t('tomorrow') : dateFilter === 'week' ? t('thisWeek') : dateFilter === 'custom' ? t('customDateRange') : t('today');
    const fourthCard = dateFilter === 'tomorrow'
        ? { label: t('pending'), value: summary.pending, icon: Clock3, tone: 'warning' }
        : { label: t('cancelled'), value: summary.cancelled, icon: XCircle, tone: 'danger' };
    const FourthCardIcon = fourthCard.icon;

    return <AppLayout title={t('reservations')}><Head title={t('reservations')} /><div className="owner-page">
        <PageHeader eyebrow={t('management')} title={t('reservations')} description={t('reservationsIntro')} actions={canManageReservations ? <Link className="btn btn-primary" href="/calendar"><CalendarPlus size={18} />{t('newReservation')}</Link> : <span className="read-only-indicator">{t('readOnly')}</span>} />
        <section className="owner-summary-grid reservation-summary-grid" aria-label={periodLabel}>
            <article><span><CalendarPlus size={18} /></span><div><small>{periodLabel}</small><strong>{summary.total}</strong></div></article>
            <article><span className="success"><CircleDollarSign size={18} /></span><div><small>{t('paid')}</small><strong>{summary.paid}</strong></div></article>
            <article><span className="warning"><WalletCards size={18} /></span><div><small>{t('unpaid')}</small><strong>{summary.unpaid}</strong></div></article>
            <article><span className={fourthCard.tone}><FourthCardIcon size={18} /></span><div><small>{fourthCard.label}</small><strong>{fourthCard.value}</strong></div></article>
            <article><span className="success"><CheckCircle2 size={18} /></span><div><small>{t('completed')}</small><strong>{summary.completed}</strong></div></article>
        </section>
        <section className="data-toolbar reservation-filter-toolbar">
            <form onSubmit={event => { event.preventDefault(); applyFilters(); }}>
                <SearchInput aria-label={t('search')} placeholder={`${t('searchReservations')}…`} value={search} onChange={event => setSearch(event.target.value)} />
            </form>
            <div className="reservation-filter-grid">
                <Field label={t('dateFilter')}><Select value={dateFilter} onChange={event => { setDateFilter(event.target.value); applyFilters({ date_filter: event.target.value }); }}>
                    <option value="today">{t('today')}</option>
                    <option value="tomorrow">{t('tomorrow')}</option>
                    <option value="week">{t('thisWeek')}</option>
                    <option value="custom">{t('customDateRange')}</option>
                </Select></Field>
                {dateFilter === 'custom' && <>
                    <Field label={t('start')}><Input type="date" value={from} onChange={event => setFrom(event.target.value)} /></Field>
                    <Field label={t('end')}><Input type="date" value={to} onChange={event => setTo(event.target.value)} /></Field>
                </>}
                <Field label={t('paymentFilter')}><Select value={paymentFilter} onChange={event => { setPaymentFilter(event.target.value); applyFilters({ payment_filter: event.target.value }); }}>
                    <option value="all">{t('all')}</option>
                    <option value="paid">{t('paid')}</option>
                    <option value="unpaid">{t('unpaid')}</option>
                    <option value="partial">{t('partiallyPaid')}</option>
                </Select></Field>
                <Field label={t('statusFilter')}><Select value={statusFilter} onChange={event => { setStatusFilter(event.target.value); applyFilters({ status_filter: event.target.value }); }}>
                    <option value="all">{t('all')}</option>
                    <option value="pending">{t('pending')}</option>
                    <option value="confirmed">{t('confirmed')}</option>
                    <option value="completed">{t('completed')}</option>
                    <option value="cancelled">{t('cancelled')}</option>
                    <option value="no_show">{t('noShow')}</option>
                </Select></Field>
                <Button type="button" onClick={() => applyFilters()}>{t('apply')}</Button>
            </div>
        </section>
        {reservations.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} action={canManageReservations ? <Link className="btn btn-primary" href="/calendar"><CalendarPlus size={17} />{t('newReservation')}</Link> : undefined} /></section> : <>
            <div className="table-wrap modern-table"><table><thead><tr><th>{t('reservationNumber')}</th><th>{t('customer')}</th><th>{t('phone')}</th><th>{t('field')}</th><th>{t('date')}</th><th>{t('time')}</th><th>{t('amount')}</th><th>{t('payment')}</th><th>{t('status')}</th><th>{t('actions')}</th></tr></thead><tbody>{reservations.data.map(reservation => <tr key={reservation.id} className="clickable-row" onClick={() => setSelected(reservation)}>
                <td data-label={t('reservationNumber')}><strong>#{String(reservation.id).padStart(4, '0')}</strong></td><td data-label={t('customer')}><strong>{reservation.customer_name}</strong></td><td data-label={t('phone')}><a href={`tel:${reservation.customer_phone}`} onClick={event => event.stopPropagation()}>{reservation.customer_phone}</a></td><td data-label={t('field')}>{reservation.football_field.name}</td><td data-label={t('date')}>{formatDate(reservation.starts_at)}</td><td data-label={t('time')}>{formatTime(reservation.starts_at)}–{formatTime(reservation.ends_at)}</td><td data-label={t('amount')}><strong>{reservation.currency} {Number(reservation.price).toFixed(2)}</strong></td><td data-label={t('payment')}><Badge value={reservation.payment_status} /></td><td data-label={t('status')}><Badge value={reservation.status} /></td><td data-label={t('actions')}><div className="reservation-actions" onClick={event => event.stopPropagation()}><button className="icon-btn bordered" onClick={() => setSelected(reservation)} title={t('view')}><Eye size={16} /></button>{canManageReservations && <><Link className="icon-btn bordered" href={`/calendar?field=${reservation.football_field_id}&reservation=${reservation.id}`} title={t('edit')}><Pencil size={16} /></Link>{reservation.payment_status !== 'paid' && <button className="icon-btn bordered success" onClick={() => router.patch(`/reservations/${reservation.id}/paid`, {}, { preserveScroll: true })} title={t('markPaid')}><CircleDollarSign size={16} /></button>}{reservation.status === 'confirmed' && <button className="icon-btn bordered success" onClick={() => router.patch(`/reservations/${reservation.id}/complete`, {}, { preserveScroll: true })} title={t('markCompleted')}><CheckCircle2 size={16} /></button>}{['pending', 'confirmed'].includes(reservation.status) && <button className="icon-btn bordered danger" onClick={() => confirm(t('cancelReservationConfirm')) && router.delete(`/reservations/${reservation.id}`, { preserveScroll: true })} title={t('cancelReservation')}><XCircle size={16} /></button>}</>}</div></td>
            </tr>)}</tbody></table></div>{reservations.last_page > 1 && <Pagination links={reservations.links} />}</>}
        <Drawer open={Boolean(selected)} title={selected?.customer_name ?? ''} subtitle={selected ? `#${String(selected.id).padStart(4, '0')}` : ''} onClose={() => setSelected(null)}>
            {selected && <div className="reservation-drawer-details">
                <a href={`tel:${selected.customer_phone}`}><UserRound size={17} /><span>{t('phone')}</span><strong>{selected.customer_phone}</strong></a>
                <div><CalendarPlus size={17} /><span>{t('field')}</span><strong>{selected.football_field.name}</strong></div>
                <div><Clock3 size={17} /><span>{t('reservationTime')}</span><strong>{formatDate(selected.starts_at)} · {formatTime(selected.starts_at)}–{formatTime(selected.ends_at)}</strong></div>
                <div><CircleDollarSign size={17} /><span>{t('amount')}</span><strong>{selected.currency} {Number(selected.price).toFixed(2)}</strong></div>
                <div><WalletCards size={17} /><span>{t('payment')}</span><strong><Badge value={selected.payment_status} /></strong></div>
                <div><CheckCircle2 size={17} /><span>{t('status')}</span><strong><Badge value={selected.status} /></strong></div>
                {selected.notes && <section><span>{t('notes')}</span><p>{selected.notes}</p></section>}
            </div>}
        </Drawer>
    </div></AppLayout>;
}
