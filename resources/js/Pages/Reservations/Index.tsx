import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarPlus, CheckCircle2, ChevronLeft, ChevronRight, CircleDollarSign, Clock3, Eye, Pencil, RotateCcw, UserRound, WalletCards, XCircle } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Modal, PageHeader, Pagination, SearchInput, Select } from '../../Components/UI';
import { DatePicker } from '../../Components/DateControls';
import { addDays, formatCalendarDate, formatDateLabel, localeCode, todayIso, type RangePeriod } from '../../lib/dateControls';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

type Filters = {
    search?: string;
    date_filter?: RangePeriod | 'week' | 'tomorrow';
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

const cancellationReasons = [
    ['customer_called', 'customerCalledToCancel'],
    ['customer_no_show', 'customerNoShow'],
    ['field_unavailable', 'fieldUnavailableReason'],
    ['duplicate_wrong_booking', 'duplicateWrongBooking'],
    ['weather_issue', 'weatherTechnicalIssue'],
    ['other', 'other'],
] as const;
const reviewActions = [
    ['reopen', 'reopenReservation'],
    ['cancel', 'cancelAppointment'],
    ['no_show', 'noShow'],
    ['void', 'voidReservation'],
    ['ignore', 'ignoreRequest'],
] as const;

export default function Reservations({ reservations, correctionRequests = [], filters, summary, timezone }: { reservations: Paginated<any>; correctionRequests?: any[]; filters: Filters; summary: Summary; timezone: string }) {
    const t = useTranslation();
    const { auth, locale } = usePage<SharedProps>().props;
    const formatterLocale = localeCode(locale);
    const canManageReservations = auth.user?.role === 'employee';
    const [search, setSearch] = useState(filters.search ?? '');
    const normalizedDateFilter: RangePeriod = filters.date_filter === 'week' ? 'this_week' : filters.date_filter === 'tomorrow' ? 'today' : filters.date_filter ?? 'today';
    const [dateFilter, setDateFilter] = useState<RangePeriod>(normalizedDateFilter);
    const [paymentFilter, setPaymentFilter] = useState(filters.payment_filter ?? 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status_filter ?? 'all');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [selected, setSelected] = useState<any>(null);
    const [cancelling, setCancelling] = useState<any>(null);
    const [reviewing, setReviewing] = useState<any>(null);
    const cancelForm = useForm({ reason: 'customer_called', note: '' });
    const reviewForm = useForm({ action: 'reopen', reason: '' });
    const selectedDate = from || to || todayIso();
    const applyFilters = (overrides: Partial<Filters> = {}) => {
        const nextDateFilter = (overrides.date_filter === 'week' ? 'this_week' : overrides.date_filter === 'tomorrow' ? 'today' : overrides.date_filter ?? dateFilter) as RangePeriod;
        router.get('/reservations', {
            search: overrides.search ?? search,
            date_filter: nextDateFilter,
            payment_filter: overrides.payment_filter ?? paymentFilter,
            status_filter: overrides.status_filter ?? statusFilter,
            ...(nextDateFilter === 'custom' ? { from: overrides.from ?? from, to: overrides.to ?? to } : {}),
        }, { preserveState: true, replace: true });
    };
    const formatDate = (value: string) => formatCalendarDate(new Date(value), locale, { day: '2-digit', month: 'short', year: 'numeric' });
    const formatSelectedDate = (value: string) => formatDateLabel(value, locale);
    const formatTime = (value: string) => new Intl.DateTimeFormat(formatterLocale, { hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date(value));
    const selectReservationDate = (date: string) => {
        setDateFilter('custom');
        setFrom(date);
        setTo(date);
        applyFilters({ date_filter: 'custom', from: date, to: date });
    };
    const moveReservationDate = (direction: -1 | 1) => {
        selectReservationDate(addDays(selectedDate, direction));
    };
    const markCompleted = (reservation: any) => {
        if (!confirm(t('completeReservationConfirm'))) return;
        router.patch(`/reservations/${reservation.id}/complete`, {}, { preserveScroll: true });
    };
    const cancelReservation = (reservation: any) => {
        cancelForm.setData({ reason: 'customer_called', note: '' });
        cancelForm.clearErrors();
        setCancelling(reservation);
    };
    const submitCancel = (event: React.FormEvent) => {
        event.preventDefault();
        if (!cancelling) return;
        cancelForm.delete(`/reservations/${cancelling.id}`, { preserveScroll: true, onSuccess: () => { setCancelling(null); setSelected(null); } });
    };
    const openReview = (request: any) => {
        reviewForm.setData({ action: 'reopen', reason: '' });
        reviewForm.clearErrors();
        setReviewing(request);
    };
    const submitReview = (event: React.FormEvent) => {
        event.preventDefault();
        if (!reviewing) return;
        reviewForm.patch(`/reservation-correction-requests/${reviewing.id}`, { preserveScroll: true, onSuccess: () => setReviewing(null) });
    };
    const correctionReasonLabel = (reason: string) => {
        switch (reason) {
            case 'completed_by_mistake': return t('markedCompletedByMistake');
            case 'payment_status_wrong': return t('paymentStatusWrong');
            case 'wrong_customer_details': return t('wrongCustomerDetails');
            case 'should_mark_no_show': return t('shouldMarkNoShow');
            case 'other': return t('other');
            default: return reason.replaceAll('_', ' ');
        }
    };
    const cancellationReasonLabel = (reason: string) => {
        switch (reason) {
            case 'customer_called': return t('customerCalledToCancel');
            case 'customer_no_show': return t('customerNoShow');
            case 'weather_issue': return t('weatherTechnicalIssue');
            case 'field_unavailable': return t('fieldUnavailableReason');
            case 'duplicate_wrong_booking': return t('duplicateWrongBooking');
            case 'other': return t('other');
            default: return reason.replaceAll('_', ' ');
        }
    };
    const periodLabel = selectedDate && from === to ? formatSelectedDate(selectedDate) : dateFilter === 'this_week' ? t('thisWeek') : dateFilter === 'last_week' ? t('last_week') : dateFilter === 'this_month' ? t('this_month') : dateFilter === 'custom' ? t('customDateRange') : dateFilter === 'yesterday' ? t('periodYesterday') : t('today');
    const fourthCard = { label: t('cancelled'), value: summary.cancelled, icon: XCircle, tone: 'danger' };
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
        {correctionRequests.length > 0 && <section className="dashboard-panel correction-request-panel">
            <div className="dashboard-section-heading"><div><span className="dashboard-eyebrow">{t('needsAttention')}</span><h2>{t('correctionRequests')}</h2><p>{t('correctionRequestsIntro')}</p></div></div>
            <div className="correction-request-list">{correctionRequests.map(request => <article key={request.id}>
                <div><AlertTriangle size={18} /><div><strong>{request.reservation.customer_name}</strong><small>{request.reservation.football_field.name} · {formatDate(request.reservation.starts_at)} · {formatTime(request.reservation.starts_at)}–{formatTime(request.reservation.ends_at)}</small></div></div>
                <p>{correctionReasonLabel(request.reason)}{request.note ? ` · ${request.note}` : ''}</p>
                <Button type="button" variant="secondary" onClick={() => openReview(request)}><RotateCcw size={16} />{t('reviewCorrection')}</Button>
            </article>)}</div>
        </section>}
        <section className="data-toolbar reservation-filter-toolbar">
            <form onSubmit={event => { event.preventDefault(); applyFilters(); }}>
                <SearchInput aria-label={t('search')} placeholder={`${t('searchReservations')}…`} value={search} onChange={event => setSearch(event.target.value)} />
            </form>
            <section className="pf-period-panel reservation-date-navigator" aria-label={t('chooseDate')}>
                <div className="pf-period-nav">
                    <Button type="button" variant="secondary" onClick={() => moveReservationDate(-1)}><ChevronLeft size={16} />{t('previousDay')}</Button>
                    <DatePicker value={selectedDate} onChange={selectReservationDate} showShortcuts={false} />
                    <Button type="button" variant="secondary" onClick={() => moveReservationDate(1)}>{t('nextDay')}<ChevronRight size={16} /></Button>
                </div>
            </section>
            <div className="reservation-filter-grid">
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
                <td data-label={t('reservationNumber')}><strong>#{String(reservation.id).padStart(4, '0')}</strong></td><td data-label={t('customer')}><strong>{reservation.customer_name}</strong></td><td data-label={t('phone')}><a href={`tel:${reservation.customer_phone}`} onClick={event => event.stopPropagation()}>{reservation.customer_phone}</a></td><td data-label={t('field')}>{reservation.football_field.name}</td><td data-label={t('date')}>{formatDate(reservation.starts_at)}</td><td data-label={t('time')}>{formatTime(reservation.starts_at)}–{formatTime(reservation.ends_at)}</td><td data-label={t('amount')}><strong>{reservation.currency} {Number(reservation.price).toFixed(2)}</strong></td><td data-label={t('payment')}><Badge value={reservation.payment_status} /></td><td data-label={t('status')}><Badge value={reservation.status} /></td><td data-label={t('actions')}><div className="reservation-actions" onClick={event => event.stopPropagation()}><button className="icon-btn bordered" onClick={() => setSelected(reservation)} title={t('view')}><Eye size={16} /></button>{canManageReservations && <><Link className="icon-btn bordered" href={`/calendar?field=${reservation.football_field_id}&reservation=${reservation.id}`} title={t('edit')}><Pencil size={16} /></Link>{reservation.payment_status !== 'paid' && <button className="icon-btn bordered success" onClick={() => router.patch(`/reservations/${reservation.id}/paid`, {}, { preserveScroll: true })} title={t('markPaid')}><CircleDollarSign size={16} /></button>}{reservation.status === 'confirmed' && <button className="icon-btn bordered success" onClick={() => markCompleted(reservation)} title={t('markCompleted')}><CheckCircle2 size={16} /></button>}{['pending', 'confirmed'].includes(reservation.status) && <button className="icon-btn bordered danger" onClick={() => cancelReservation(reservation)} title={t('cancelReservation')}><XCircle size={16} /></button>}</>}</div></td>
            </tr>)}</tbody></table></div>{reservations.last_page > 1 && <Pagination links={reservations.links} />}</>}
        <Drawer open={Boolean(selected)} title={selected?.customer_name ?? ''} subtitle={selected ? `#${String(selected.id).padStart(4, '0')}` : ''} onClose={() => setSelected(null)}>
            {selected && <div className="reservation-drawer-details">
                <a href={`tel:${selected.customer_phone}`}><UserRound size={17} /><span>{t('phone')}</span><strong>{selected.customer_phone}</strong></a>
                <div><CalendarPlus size={17} /><span>{t('field')}</span><strong>{selected.football_field.name}</strong></div>
                <div><Clock3 size={17} /><span>{t('reservationTime')}</span><strong>{formatDate(selected.starts_at)} · {formatTime(selected.starts_at)}–{formatTime(selected.ends_at)}</strong></div>
                <div><CircleDollarSign size={17} /><span>{t('amount')}</span><strong>{selected.currency} {Number(selected.price).toFixed(2)}</strong></div>
                <div><WalletCards size={17} /><span>{t('payment')}</span><strong><Badge value={selected.payment_status} /></strong></div>
                <div><CheckCircle2 size={17} /><span>{t('status')}</span><strong><Badge value={selected.status} /></strong></div>
                {selected.notes && <section><span>{t('bookingPrivateNote')}</span><p>{selected.notes}</p></section>}
                {selected.cancellation_reason && <section><span>{t('cancellationReason')}</span><p>{cancellationReasonLabel(selected.cancellation_reason)}{selected.cancellation_note ? ` · ${selected.cancellation_note}` : ''}</p></section>}
            </div>}
        </Drawer>
        <Modal open={Boolean(cancelling)} title={t('cancelAppointment')} onClose={() => setCancelling(null)}>
            <form onSubmit={submitCancel} className="board-reservation-form">
                <p className="modal-copy">{t('cancelReservationHelp')}</p>
                <Field label={t('cancellationReason')} error={cancelForm.errors.reason} required><Select value={cancelForm.data.reason} onChange={event => cancelForm.setData('reason', event.target.value)}>{cancellationReasons.map(([value, label]) => <option key={value} value={value}>{t(label)}</option>)}</Select></Field>
                <Field label={t('cancellationNote')} error={cancelForm.errors.note} required={cancelForm.data.reason === 'other'}><textarea className="input" value={cancelForm.data.note} onChange={event => cancelForm.setData('note', event.target.value)} /></Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setCancelling(null)}>{t('close')}</Button><Button variant="danger" disabled={cancelForm.processing}>{t('cancelAppointment')}</Button></div>
            </form>
        </Modal>
        <Modal open={Boolean(reviewing)} title={t('reviewCorrection')} onClose={() => setReviewing(null)}>
            <form onSubmit={submitReview} className="board-reservation-form">
                <Field label={t('actions')} error={reviewForm.errors.action} required><Select value={reviewForm.data.action} onChange={event => reviewForm.setData('action', event.target.value)}>{reviewActions.map(([value, label]) => <option key={value} value={value}>{t(label)}</option>)}</Select></Field>
                <Field label={t('reviewReason')} error={reviewForm.errors.reason} required><textarea className="input" value={reviewForm.data.reason} onChange={event => reviewForm.setData('reason', event.target.value)} /></Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setReviewing(null)}>{t('close')}</Button><Button disabled={reviewForm.processing}>{t('save')}</Button></div>
            </form>
        </Modal>
    </div></AppLayout>;
}
