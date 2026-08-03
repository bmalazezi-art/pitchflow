import { router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays, Check, ChevronLeft, ChevronRight, Clock3, CreditCard,
    Edit3, Phone, Plus, RefreshCw, Trash2, UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Badge, Button, Drawer, Field, Input, Modal, Select } from './UI';
import { SingleDateNavigator } from './DateControls';
import { formatCalendarDate } from '../lib/dateControls';
import { useTranslation } from '../lib/i18n';
import { getSlotStatus, isSlotBlockedByReservation } from '../lib/slotStatus';
import type { SharedProps } from '../types';

interface OperatingHour {
    day_of_week: number;
    opening_time: string;
    closing_time: string;
    is_closed: boolean;
}

interface OperatingHourOverride {
    date: string;
    opening_time: string | null;
    closing_time: string | null;
    is_closed: boolean;
}

interface BoardField {
    id: number;
    name: string;
    status: string;
    opening_time: string;
    closing_time: string;
    operating_hours: OperatingHour[];
    operating_hour_overrides: OperatingHourOverride[];
}

interface BoardReservation {
    id: number;
    football_field_id: number;
    customer_name: string;
    customer_phone: string;
    starts_at: string;
    ends_at: string;
    status: string;
    payment_status: string;
    paid_amount: number | string;
    is_walk_in: boolean;
    notes: string | null;
    football_field: { id: number; name: string };
    customer?: { total_reservations: number; no_shows: number; reliability_status: string };
    waiting_list_requests?: Array<{
        id: number;
        customer_name: string;
        phone: string;
        note?: string | null;
        status: string;
        created_at: string;
    }>;
}

interface BookingBoardProps {
    reservations: BoardReservation[];
    fields: BoardField[];
    timezone: string;
    selectedField?: number | null;
    selectedReservation?: number | null;
    initialDate?: string;
}

type ScheduleWindow = { start: number; end: number };

const formDefaults = {
    customer_name: '', customer_phone: '', football_field_id: '' as number | '', starts_at: '', ends_at: '',
    status: 'confirmed', payment_status: 'unpaid', paid_amount: 0, is_walk_in: false, notes: '',
};
const cancellationReasons = [
    ['customer_called', 'customerCalledToCancel'],
    ['customer_no_show', 'customerNoShow'],
    ['field_unavailable', 'fieldUnavailableReason'],
    ['duplicate_wrong_booking', 'duplicateWrongBooking'],
    ['weather_issue', 'weatherTechnicalIssue'],
    ['other', 'other'],
] as const;
const correctionReasons = [
    ['completed_by_mistake', 'markedCompletedByMistake'],
    ['payment_status_wrong', 'paymentStatusWrong'],
    ['wrong_customer_details', 'wrongCustomerDetails'],
    ['should_mark_no_show', 'shouldMarkNoShow'],
    ['other', 'other'],
] as const;
const completedMistakeActions = [
    ['reopen', 'reopenReservation'],
    ['cancel', 'cancelAppointment'],
    ['no_show', 'noShow'],
] as const;

const timeMinutes = (value: string) => {
    const [hours, minutes] = value.slice(0, 5).split(':').map(Number);
    return hours * 60 + minutes;
};

const pad = (value: number) => String(value).padStart(2, '0');
const displayTime = (minutes: number) => `${pad(Math.floor((minutes % 1440) / 60))}:${pad(minutes % 60)}`;
const sanitizePhoneInput = (value: string) => {
    const hasLeadingPlus = value.trimStart().startsWith('+');
    const withoutPluses = value.replace(/\+/g, '');
    const readablePhone = withoutPluses.replace(/[^\d ]/g, '').replace(/\s{2,}/g, ' ');

    return hasLeadingPlus ? `+${readablePhone.trimStart()}` : readablePhone;
};
const addDays = (date: string, days: number) => {
    const value = new Date(`${date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);
    return value.toISOString().slice(0, 10);
};
const startOfWeek = (date: string) => {
    const value = new Date(`${date}T12:00:00Z`);
    const offset = (value.getUTCDay() + 6) % 7;
    value.setUTCDate(value.getUTCDate() - offset);
    return value.toISOString().slice(0, 10);
};
const dayOfWeek = (date: string) => new Date(`${date}T12:00:00Z`).getUTCDay();

const zonedParts = (value: string | Date, timezone: string) => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find(item => item.type === type)?.value ?? '';
    return { date: `${part('year')}-${part('month')}-${part('day')}`, time: `${part('hour')}:${part('minute')}` };
};

const rawScheduleFor = (field: BoardField, date: string): ScheduleWindow | null => {
    const override = field.operating_hour_overrides.find(item => item.date.slice(0, 10) === date);
    const weekly = field.operating_hours.find(item => item.day_of_week === dayOfWeek(date));
    if (override?.is_closed || (!override && weekly?.is_closed)) return null;
    const opening = override?.opening_time ?? weekly?.opening_time ?? field.opening_time;
    const closing = override?.closing_time ?? weekly?.closing_time ?? field.closing_time;
    const start = timeMinutes(opening);
    let end = timeMinutes(closing);
    if (end <= start) end += 1440;
    return { start, end };
};

const scheduleFor = (field: BoardField, date: string): ScheduleWindow[] => {
    const windows: ScheduleWindow[] = [];
    const previous = rawScheduleFor(field, addDays(date, -1));
    const current = rawScheduleFor(field, date);

    if (previous && previous.end > 1440) {
        windows.push({ start: 0, end: previous.end - 1440 });
    }

    if (current) {
        windows.push(current);
    }

    return windows;
};

const formatDate = (date: string, locale: string, options: Intl.DateTimeFormatOptions = {}) => formatCalendarDate(
    new Date(`${date}T12:00:00Z`),
    locale,
    { weekday: 'long', day: 'numeric', month: 'long', ...options },
);

export default function EmployeeBookingBoard({ reservations, fields, timezone, selectedField, selectedReservation, initialDate }: BookingBoardProps) {
    const t = useTranslation();
    const { locale, flash } = usePage<SharedProps>().props;
    const today = zonedParts(new Date(), timezone).date;
    const initialReservation = selectedReservation ? reservations.find(item => item.id === selectedReservation) ?? null : null;
    const initialFieldIndex = Math.max(0, fields.findIndex(field => field.id === (selectedField ?? initialReservation?.football_field_id)));
    const [selectedDate, setSelectedDate] = useState(initialReservation ? zonedParts(initialReservation.starts_at, timezone).date : initialDate ?? today);
    const [view, setView] = useState<'today' | 'tomorrow' | 'week'>('today');
    const [mobileFieldIndex, setMobileFieldIndex] = useState(initialFieldIndex);
    const [drawerReservation, setDrawerReservation] = useState<BoardReservation | null>(initialReservation);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BoardReservation | null>(null);
    const [cancelling, setCancelling] = useState<BoardReservation | null>(null);
    const [correcting, setCorrecting] = useState<BoardReservation | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [copiedWaitingListId, setCopiedWaitingListId] = useState<number | null>(null);
    const touchStart = useRef<number | null>(null);
    const form = useForm(formDefaults);
    const cancelForm = useForm({ reason: 'customer_called', note: '' });
    const correctionForm = useForm({ reason: 'completed_by_mistake', action: 'reopen', note: '' });
    const drawerReservationId = drawerReservation?.id;

    useEffect(() => {
        if (!drawerReservationId) return;
        const fresh = reservations.find(reservation => reservation.id === drawerReservationId);
        if (fresh) setDrawerReservation(fresh);
    }, [drawerReservationId, reservations]);

    const schedules = useMemo(() => new Map(fields.map(field => [field.id, scheduleFor(field, selectedDate)])), [fields, selectedDate]);
    const slots = useMemo(() => {
        const active = [...schedules.values()].flat();
        if (!active.length) return [];
        const start = Math.min(...active.map(schedule => schedule.start));
        const end = Math.max(...active.map(schedule => schedule.end));
        return Array.from({ length: Math.ceil((end - start) / 60) }, (_, index) => start + index * 60);
    }, [schedules]);
    const weekDates = useMemo(() => {
        const firstDay = startOfWeek(selectedDate);
        return Array.from({ length: 7 }, (_, index) => addDays(firstDay, index));
    }, [selectedDate]);

    const loadDateRange = (date: string, nextView: 'today' | 'tomorrow' | 'week' = view) => {
        const from = nextView === 'week' ? startOfWeek(date) : date;
        const to = nextView === 'week' ? addDays(from, 6) : date;
        router.get('/calendar', { from, to }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['reservations', 'fields', 'selectedField', 'selectedReservation'],
        });
    };

    const reservationAt = (fieldId: number, slot: number) => reservations.find(reservation => {
        if (reservation.football_field_id !== fieldId || !isSlotBlockedByReservation(reservation.status as any)) return false;
        const starts = zonedParts(reservation.starts_at, timezone);
        const ends = zonedParts(reservation.ends_at, timezone);
        const start = timeMinutes(starts.time) + (starts.date === selectedDate ? 0 : starts.date === addDays(selectedDate, 1) ? 1440 : -10000);
        let end = timeMinutes(ends.time) + (ends.date === selectedDate ? 0 : ends.date === addDays(selectedDate, 1) ? 1440 : -10000);
        if (end <= start) end += 1440;
        return slot < end && slot + 60 > start;
    });

    const selectBoardDate = (date: string, nextView: 'today' | 'tomorrow' | 'week' = view) => {
        setView(nextView);
        setSelectedDate(date);
        loadDateRange(date, nextView);
    };

    const openNew = (fieldId = fields[0]?.id ?? '', slot?: number) => {
        const startMinutes = slot ?? schedules.get(Number(fieldId))?.[0]?.start ?? 12 * 60;
        const startDate = startMinutes >= 1440 ? addDays(selectedDate, 1) : selectedDate;
        const start = `${startDate}T${displayTime(startMinutes)}`;
        const endMinutes = startMinutes + 60;
        const endDate = endMinutes >= 1440 ? addDays(selectedDate, 1) : selectedDate;
        setEditing(null);
        form.setData({ ...formDefaults, football_field_id: fieldId, starts_at: start, ends_at: `${endDate}T${displayTime(endMinutes)}` });
        form.clearErrors();
        setModalOpen(true);
    };

    const openEdit = (reservation: BoardReservation) => {
        const start = zonedParts(reservation.starts_at, timezone);
        const end = zonedParts(reservation.ends_at, timezone);
        setEditing(reservation);
        form.setData({
            customer_name: reservation.customer_name, customer_phone: reservation.customer_phone,
            football_field_id: reservation.football_field_id, starts_at: `${start.date}T${start.time}`,
            ends_at: `${end.date}T${end.time}`, status: reservation.status, payment_status: reservation.payment_status,
            paid_amount: Number(reservation.paid_amount), is_walk_in: reservation.is_walk_in, notes: reservation.notes ?? '',
        });
        form.clearErrors();
        setDrawerReservation(null);
        setModalOpen(true);
    };

    const setDateOrTime = (part: 'date' | 'time', value: string) => {
        const [date, time] = form.data.starts_at.split('T');
        const nextStart = part === 'date' ? `${value}T${time}` : `${date}T${value}`;
        const duration = Math.max(1, Math.round((new Date(form.data.ends_at).getTime() - new Date(form.data.starts_at).getTime()) / 3600000) || 1);
        const end = new Date(`${nextStart}:00`);
        end.setHours(end.getHours() + duration);
        const localEnd = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}`;
        form.setData(data => ({ ...data, starts_at: nextStart, ends_at: localEnd }));
    };

    const setDuration = (hours: number) => {
        const end = new Date(`${form.data.starts_at}:00`);
        end.setHours(end.getHours() + hours);
        form.setData('ends_at', `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}`);
    };

    const duration = Math.max(1, Math.round((new Date(form.data.ends_at).getTime() - new Date(form.data.starts_at).getTime()) / 3600000) || 1);
    const waitingCountLabel = (count: number) => locale === 'sq' ? `${count} në pritje` : `${count} waiting`;
    const waitingSummary = (count: number) => locale === 'sq' ? `Ky termin ka ${count} persona në pritje.` : `This slot has ${count} people waiting.`;
    const formatWaitingCreated = (value: string) => new Intl.DateTimeFormat(locale === 'sq' ? 'sq-AL' : 'en-GB', {
        timeZone: timezone,
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { setModalOpen(false); setEditing(null); } };
        if (editing) form.put(`/reservations/${editing.id}`, options);
        else form.post('/reservations', options);
    };
    const copyWaitingMessage = async (id: number, message: string) => {
        await navigator.clipboard.writeText(message);
        setCopiedWaitingListId(id);
        window.setTimeout(() => setCopiedWaitingListId(null), 1800);
    };
    const openCancel = (reservation: BoardReservation) => {
        cancelForm.setData({ reason: 'customer_called', note: '' });
        cancelForm.clearErrors();
        setCancelling(reservation);
        setActionError(null);
    };
    const submitCancel = (event: React.FormEvent) => {
        event.preventDefault();
        if (!cancelling) return;
        cancelForm.delete(`/reservations/${cancelling.id}`, {
            preserveScroll: true,
            preserveState: true,
            only: ['reservations', 'fields', 'flash', 'errors'],
            onSuccess: () => { setCancelling(null); setDrawerReservation(null); setActionError(null); },
        });
    };
    const openCorrection = (reservation: BoardReservation) => {
        correctionForm.setData({ reason: 'completed_by_mistake', action: 'reopen', note: '' });
        correctionForm.clearErrors();
        setCorrecting(reservation);
        setActionError(null);
    };
    const changeCorrectionReason = (reason: string) => {
        correctionForm.setData(data => ({
            ...data,
            reason,
            action: reason === 'completed_by_mistake' ? 'reopen' : '',
        }));
    };
    const submitCorrection = (event: React.FormEvent) => {
        event.preventDefault();
        if (!correcting) return;
        correctionForm.post(`/reservations/${correcting.id}/correction-requests`, {
            preserveScroll: true,
            preserveState: true,
            only: ['reservations', 'fields', 'flash', 'errors'],
            onSuccess: () => { setCorrecting(null); setDrawerReservation(null); setActionError(null); },
        });
    };

    return <div className="booking-board-page">
        <header className="booking-board-heading">
            <div><span>{t('employeeWorkspace')}</span><h1>{t('bookingBoard')}</h1><p>{t('bookingBoardIntro')}</p></div>
            <Button onClick={() => openNew()} disabled={!fields.length}><Plus size={18} />{t('newReservation')}</Button>
        </header>
        {flash.waiting_list_requests && <section className="waiting-list-panel">
            <header>
                <div><span>{t('peopleWaitingForSlot')}</span><h2>{waitingSummary(flash.waiting_list_requests.count)}</h2><p>{flash.waiting_list_requests.field_name} · {flash.waiting_list_requests.start_time}</p></div>
            </header>
            <div>{flash.waiting_list_requests.requests.map(item => <article key={item.id}>
                <div><strong>{item.customer_name}</strong><a href={`tel:${item.phone}`}>{item.phone}</a>{item.note && <small>{item.note}</small>}</div>
                <div>
                    <Button type="button" variant="secondary" onClick={() => copyWaitingMessage(item.id, item.message)}>{copiedWaitingListId === item.id ? t('whatsappMessageCopied') : t('copyWhatsappMessage')}</Button>
                    <Button type="button" variant="success" onClick={() => router.patch(`/waiting-list/${item.id}/notified`, {}, { preserveScroll: true, only: ['flash'] })}>{t('markAsNotified')}</Button>
                </div>
            </article>)}</div>
        </section>}

        <section className="booking-board-shell">
            <div className="booking-board-toolbar">
                <SingleDateNavigator value={selectedDate} mode={view} showWeek onModeChange={setView} onChange={(date, nextView = view) => selectBoardDate(date, nextView)} />
                <div className="board-current-date"><CalendarDays size={17} /><strong>{formatDate(selectedDate, locale)}</strong></div>
                <button className="board-refresh" onClick={() => router.reload({ only: ['reservations', 'fields'] })} title={t('refreshBoard')} aria-label={t('refreshBoard')}><RefreshCw size={17} /></button>
            </div>

            {view === 'week' && <div className="board-week-strip">{weekDates.map(date => <button key={date} className={selectedDate === date ? 'active' : ''} onClick={() => selectBoardDate(date, 'week')}><span>{formatCalendarDate(new Date(`${date}T12:00:00Z`), locale, { weekday: 'short' })}</span><strong>{new Date(`${date}T12:00:00Z`).getUTCDate()}</strong></button>)}</div>}
            <p className="board-grid-helper">{t('boardGridHelp')}</p>

            {!fields.length ? <div className="board-empty"><CalendarDays size={28} /><h2>{t('noAssignedFields')}</h2><p>{t('assignedFieldsIntro')}</p></div> : <>
                <div className="mobile-field-switcher">
                    <button onClick={() => setMobileFieldIndex(index => Math.max(0, index - 1))} disabled={mobileFieldIndex === 0} aria-label={t('previousField')}><ChevronLeft size={19} /></button>
                    <div><span>{t('field')}</span><strong>{fields[mobileFieldIndex]?.name}</strong></div>
                    <button onClick={() => setMobileFieldIndex(index => Math.min(fields.length - 1, index + 1))} disabled={mobileFieldIndex === fields.length - 1} aria-label={t('nextField')}><ChevronRight size={19} /></button>
                </div>
                <div className="booking-board-scroll" onTouchStart={event => { touchStart.current = event.touches[0].clientX; }} onTouchEnd={event => {
                    if (touchStart.current === null) return;
                    const distance = event.changedTouches[0].clientX - touchStart.current;
                    if (Math.abs(distance) > 55) setMobileFieldIndex(index => Math.max(0, Math.min(fields.length - 1, index + (distance < 0 ? 1 : -1))));
                    touchStart.current = null;
                }}>
                    <div className="booking-board-grid" style={{ '--board-fields': fields.length } as React.CSSProperties}>
                        <div className="board-corner"><Clock3 size={16} />{t('time')}</div>
                        {fields.map((field, index) => <div key={field.id} className={`board-field-header board-field-${index} ${index === mobileFieldIndex ? 'mobile-active' : ''}`}><strong>{field.name}</strong><Badge value={field.status} /></div>)}
                        {slots.map(slot => <div className="board-row" key={slot}>
                            <div className="board-time"><strong>{displayTime(slot)}</strong><span>{displayTime(slot + 60)}</span></div>
                            {fields.map((field, index) => {
                                const schedule = schedules.get(field.id) ?? [];
                                const reservation = reservationAt(field.id, slot);
                                const availableWindow = schedule.some(window => slot >= window.start && slot + 60 <= window.end);
                                const unavailable = field.status !== 'active' || !availableWindow;
                                const slotDate = slot >= 1440 ? addDays(selectedDate, 1) : selectedDate;
                                const status = unavailable ? 'closed' : getSlotStatus({
                                    selectedDate: slotDate,
                                    startTime: displayTime(slot),
                                    endTime: displayTime(slot + 60),
                                    reservationStatus: reservation?.status as any,
                                    timezone,
                                });
                                const state = unavailable ? 'maintenance' : reservation ? (reservation.status === 'pending' ? 'pending' : 'reserved') : status;
                                const waitingCount = reservation?.waiting_list_requests?.length ?? 0;
                                return <button key={field.id} className={`board-slot ${state} board-field-${index} ${index === mobileFieldIndex ? 'mobile-active' : ''}`} disabled={unavailable || (['past', 'current'].includes(status) && !reservation)} onClick={() => reservation ? setDrawerReservation(reservation) : openNew(field.id, slot)}>
                                    <span className="board-slot-dot" />
                                    <span className="board-slot-copy"><strong>{reservation ? reservation.customer_name : t(state === 'maintenance' ? 'maintenance' : state === 'past' ? 'past' : state === 'current' ? 'currentSlot' : 'free')}</strong>{reservation ? <span className="board-slot-badges"><Badge value={reservation.payment_status} />{reservation.status !== 'confirmed' && <Badge value={reservation.status} />}</span> : <small>{unavailable ? t('closed') : status === 'current' ? t('currentSlot') : t('clickToBook')}</small>}</span>
                                    {waitingCount > 0 ? <span className="board-waiting-badge">{waitingCountLabel(waitingCount)}</span> : reservation?.payment_status === 'paid' && <Check size={15} />}
                                </button>;
                            })}
                        </div>)}
                    </div>
                </div>
                <div className="board-legend"><span className="available">{t('available')}</span><span className="reserved">{t('reserved')}</span><span className="pending">{t('pending')}</span><span className="maintenance">{t('maintenance')}</span><span className="past">{t('past')}</span></div>
            </>}
        </section>

        <Drawer open={Boolean(drawerReservation)} title={drawerReservation?.customer_name ?? ''} subtitle={drawerReservation?.football_field.name} onClose={() => { setDrawerReservation(null); setActionError(null); }} footer={drawerReservation && (() => {
            const isStarted = new Date(drawerReservation.starts_at).getTime() <= Date.now();
            const isCompleted = drawerReservation.status === 'completed';
            const isTerminal = ['completed', 'cancelled', 'late_cancelled', 'no_show', 'voided'].includes(drawerReservation.status);
            const canEdit = !isStarted && !isTerminal;
            const actionOptions = {
                preserveScroll: true,
                preserveState: true,
                only: ['reservations', 'fields', 'flash', 'errors'],
                onSuccess: () => setActionError(null),
                onError: (errors: Record<string, string>) => setActionError(Object.values(errors)[0] ?? null),
            };
            return <>
                <Button variant="secondary" disabled={!canEdit} title={!canEdit ? (isCompleted ? t('completedReservationReadOnly') : t('pastReservationEditDisabled')) : undefined} onClick={() => canEdit ? openEdit(drawerReservation) : setActionError(isCompleted ? t('completedReservationReadOnly') : t('pastReservationEditDisabled'))}><Edit3 size={16} />{t('edit')}</Button>
                {!isTerminal && drawerReservation.payment_status !== 'paid' && <Button variant="success" onClick={() => { setActionError(null); router.patch(`/reservations/${drawerReservation.id}/paid`, {}, actionOptions); }}><CreditCard size={16} />{t('markPaid')}</Button>}
                {drawerReservation.status === 'confirmed' && <Button variant="secondary" onClick={() => confirm(t('completeReservationConfirm')) && router.patch(`/reservations/${drawerReservation.id}/complete`, {}, actionOptions)}><Check size={16} />{t('markCompleted')}</Button>}
                {['pending', 'confirmed'].includes(drawerReservation.status) && <Button variant="danger" onClick={() => openCancel(drawerReservation)}><Trash2 size={16} />{t('cancelAppointment')}</Button>}
                {isCompleted && <Button variant="secondary" onClick={() => openCorrection(drawerReservation)}><Edit3 size={16} />{t('reportProblem')}</Button>}
            </>;
        })()}>
            {drawerReservation && <div className="reservation-drawer-details">
                {actionError && <div className="drawer-action-error"><span>{t('needsAttention')}</span><strong>{actionError}</strong></div>}
                {drawerReservation.status === 'completed' && <div className="drawer-readonly-note"><span>{t('readOnly')}</span><strong>{t('completedReservationReadOnly')}</strong></div>}
                <div><UserRound size={17} /><span>{t('customer')}</span><strong>{drawerReservation.customer_name}</strong></div>
                <a href={`tel:${drawerReservation.customer_phone}`}><Phone size={17} /><span>{t('phone')}</span><strong>{drawerReservation.customer_phone}</strong></a>
                <div><Clock3 size={17} /><span>{t('reservationTime')}</span><strong>{formatCalendarDate(new Date(drawerReservation.starts_at), locale, { weekday: 'short', day: 'numeric', month: 'short' })} · {new Intl.DateTimeFormat(locale === 'sq' ? 'sq-AL' : 'en-GB', { timeZone: timezone, hour: '2-digit', minute: '2-digit' }).format(new Date(drawerReservation.starts_at))}</strong></div>
                <div><CreditCard size={17} /><span>{t('payment')}</span><strong><Badge value={drawerReservation.payment_status} /></strong></div>
                <div><CalendarDays size={17} /><span>{t('status')}</span><strong><Badge value={drawerReservation.status} /></strong></div>
                {(drawerReservation.waiting_list_requests?.length ?? 0) > 0 && <section className="drawer-waiting-list"><span>{t('waitingList')}</span><div>{drawerReservation.waiting_list_requests?.map(item => <article key={item.id}>
                    <div><strong>{item.customer_name}</strong><small>{formatWaitingCreated(item.created_at)}</small></div>
                    <a href={`tel:${item.phone}`}>{item.phone}</a>
                    {item.note && <p>{item.note}</p>}
                </article>)}</div></section>}
                <section><span>{t('bookingPrivateNote')}</span><p>{drawerReservation.notes || t('noInternalNotes')}</p></section>
            </div>}
        </Drawer>

        <Modal open={modalOpen} title={editing ? t('editReservation') : t('newReservation')} onClose={() => setModalOpen(false)}><form onSubmit={submit} className="board-reservation-form"><div className="form-grid">
            <Field label={t('customerName')} error={form.errors.customer_name} required><Input autoFocus value={form.data.customer_name} onChange={event => form.setData('customer_name', event.target.value)} /></Field>
            <Field label={t('phone')} error={form.errors.customer_phone} required><Input inputMode="tel" autoComplete="tel" value={form.data.customer_phone} onChange={event => form.setData('customer_phone', sanitizePhoneInput(event.target.value))} /></Field>
            <Field label={t('selectField')} error={form.errors.football_field_id} required><Select value={form.data.football_field_id} onChange={event => form.setData('football_field_id', Number(event.target.value))}>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field>
            {editing && <Field label={t('status')}><Select value={form.data.status} onChange={event => form.setData('status', event.target.value)}><option value="pending">{t('pending')}</option><option value="confirmed">{t('confirmed')}</option><option value="completed">{t('completed')}</option><option value="no_show">{t('noShow')}</option></Select></Field>}
            <Field label={t('date')} error={form.errors.starts_at} required><Input type="date" value={form.data.starts_at.split('T')[0]} onChange={event => setDateOrTime('date', event.target.value)} /></Field>
            <Field label={t('time')} error={form.errors.starts_at} required><Input type="time" value={form.data.starts_at.split('T')[1] ?? ''} onChange={event => setDateOrTime('time', event.target.value)} /></Field>
            <Field label={t('duration')} error={form.errors.ends_at} required><Select value={duration} onChange={event => setDuration(Number(event.target.value))}>{[1, 2, 3, 4].map(hours => <option key={hours} value={hours}>{hours} {hours === 1 ? t('hour') : t('hours')}</option>)}</Select></Field>
            <Field label={t('payment')} error={form.errors.payment_status}><Select value={form.data.payment_status} onChange={event => form.setData('payment_status', event.target.value)}><option value="unpaid">{t('unpaid')}</option><option value="partial">{t('partial')}</option><option value="paid">{t('paid')}</option></Select></Field>
            {form.data.payment_status !== 'unpaid' && <Field label={t('amountPaid')}><Input type="number" min="0" step=".01" value={form.data.paid_amount} onChange={event => form.setData('paid_amount', Number(event.target.value))} /></Field>}
            <Field label={t('bookingPrivateNote')} error={form.errors.notes}><textarea className="input" value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} /></Field>
            <label className="check-row"><input type="checkbox" checked={form.data.is_walk_in} onChange={event => form.setData('is_walk_in', event.target.checked)} /> {t('walkIn')}</label>
        </div>{flash.slot_suggestions && flash.slot_suggestions.length > 0 && <div className="form-callout"><strong>{t('suggestedSlots')}</strong><div className="actions">{flash.slot_suggestions.map(slot => <Button key={slot.starts_at} type="button" variant="secondary" onClick={() => form.setData(data => ({ ...data, starts_at: slot.starts_at, ends_at: slot.ends_at }))}>{slot.label}</Button>)}</div></div>}<div className="form-actions"><Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>{t('close')}</Button><Button disabled={form.processing}>{t('save')}</Button></div></form></Modal>
        <Modal open={Boolean(cancelling)} title={t('cancelAppointment')} onClose={() => setCancelling(null)}>
            <form onSubmit={submitCancel} className="board-reservation-form">
                <p className="modal-copy">{t('cancelReservationHelp')}</p>
                <Field label={t('cancellationReason')} error={cancelForm.errors.reason} required><Select value={cancelForm.data.reason} onChange={event => cancelForm.setData('reason', event.target.value)}>{cancellationReasons.map(([value, label]) => <option key={value} value={value}>{t(label)}</option>)}</Select></Field>
                <Field label={t('cancellationNote')} error={cancelForm.errors.note} required={cancelForm.data.reason === 'other'}><textarea className="input" value={cancelForm.data.note} onChange={event => cancelForm.setData('note', event.target.value)} /></Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setCancelling(null)}>{t('close')}</Button><Button variant="danger" disabled={cancelForm.processing}>{t('cancelAppointment')}</Button></div>
            </form>
        </Modal>
        <Modal open={Boolean(correcting)} title={t('reportProblem')} onClose={() => setCorrecting(null)}>
            <form onSubmit={submitCorrection} className="board-reservation-form">
                <Field label={t('correctionReason')} error={correctionForm.errors.reason} required><Select value={correctionForm.data.reason} onChange={event => changeCorrectionReason(event.target.value)}>{correctionReasons.map(([value, label]) => <option key={value} value={value}>{t(label)}</option>)}</Select></Field>
                {correctionForm.data.reason === 'completed_by_mistake' && <Field label={t('whatShouldHappen')} error={correctionForm.errors.action} required><Select value={correctionForm.data.action} onChange={event => correctionForm.setData('action', event.target.value)}>{completedMistakeActions.map(([value, label]) => <option key={value} value={value}>{t(label)}</option>)}</Select></Field>}
                <Field label={t('correctionNote')} error={correctionForm.errors.note} required={correctionForm.data.reason === 'other' || correctionForm.data.action === 'cancel'}><textarea className="input" value={correctionForm.data.note} onChange={event => correctionForm.setData('note', event.target.value)} /></Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setCorrecting(null)}>{t('close')}</Button><Button disabled={correctionForm.processing}>{t('reportProblem')}</Button></div>
            </form>
        </Modal>
    </div>;
}
