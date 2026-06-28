import { router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays, Check, ChevronLeft, ChevronRight, Clock3, CreditCard,
    Edit3, Phone, Plus, RefreshCw, Trash2, UserRound,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Badge, Button, Drawer, Field, Input, Modal, Select } from './UI';
import { useTranslation } from '../lib/i18n';
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
}

interface BookingBoardProps {
    reservations: BoardReservation[];
    fields: BoardField[];
    timezone: string;
    selectedField?: number | null;
    selectedReservation?: number | null;
}

const formDefaults = {
    customer_name: '', customer_phone: '', football_field_id: '' as number | '', starts_at: '', ends_at: '',
    status: 'confirmed', payment_status: 'unpaid', paid_amount: 0, is_walk_in: false, notes: '',
};

const timeMinutes = (value: string) => {
    const [hours, minutes] = value.slice(0, 5).split(':').map(Number);
    return hours * 60 + minutes;
};

const pad = (value: number) => String(value).padStart(2, '0');
const displayTime = (minutes: number) => `${pad(Math.floor((minutes % 1440) / 60))}:${pad(minutes % 60)}`;
const addDays = (date: string, days: number) => {
    const value = new Date(`${date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);
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

const scheduleFor = (field: BoardField, date: string) => {
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

const formatDate = (date: string, locale: string, options: Intl.DateTimeFormatOptions = {}) => new Intl.DateTimeFormat(
    locale === 'sq' ? 'sq-AL' : 'en-GB',
    { weekday: 'long', day: 'numeric', month: 'long', ...options },
).format(new Date(`${date}T12:00:00Z`));

export default function EmployeeBookingBoard({ reservations, fields, timezone, selectedField, selectedReservation }: BookingBoardProps) {
    const t = useTranslation();
    const { locale, flash } = usePage<SharedProps>().props;
    const today = zonedParts(new Date(), timezone).date;
    const initialReservation = selectedReservation ? reservations.find(item => item.id === selectedReservation) ?? null : null;
    const initialFieldIndex = Math.max(0, fields.findIndex(field => field.id === (selectedField ?? initialReservation?.football_field_id)));
    const [selectedDate, setSelectedDate] = useState(initialReservation ? zonedParts(initialReservation.starts_at, timezone).date : today);
    const [view, setView] = useState<'today' | 'tomorrow' | 'week'>('today');
    const [mobileFieldIndex, setMobileFieldIndex] = useState(initialFieldIndex);
    const [drawerReservation, setDrawerReservation] = useState<BoardReservation | null>(initialReservation);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BoardReservation | null>(null);
    const touchStart = useRef<number | null>(null);
    const form = useForm(formDefaults);

    const schedules = useMemo(() => new Map(fields.map(field => [field.id, scheduleFor(field, selectedDate)])), [fields, selectedDate]);
    const slots = useMemo(() => {
        const active = [...schedules.values()].filter((schedule): schedule is { start: number; end: number } => schedule !== null);
        if (!active.length) return [];
        const start = Math.min(...active.map(schedule => schedule.start));
        const end = Math.max(...active.map(schedule => schedule.end));
        return Array.from({ length: Math.ceil((end - start) / 60) }, (_, index) => start + index * 60);
    }, [schedules]);
    const weekDates = useMemo(() => Array.from({ length: 7 }, (_, index) => addDays(today, index)), [today]);

    const reservationAt = (fieldId: number, slot: number) => reservations.find(reservation => {
        if (reservation.football_field_id !== fieldId || ['cancelled', 'late_cancelled', 'no_show'].includes(reservation.status)) return false;
        const starts = zonedParts(reservation.starts_at, timezone);
        const ends = zonedParts(reservation.ends_at, timezone);
        const start = timeMinutes(starts.time) + (starts.date === selectedDate ? 0 : starts.date === addDays(selectedDate, 1) ? 1440 : -10000);
        let end = timeMinutes(ends.time) + (ends.date === selectedDate ? 0 : ends.date === addDays(selectedDate, 1) ? 1440 : -10000);
        if (end <= start) end += 1440;
        return slot < end && slot + 60 > start;
    });

    const isPast = (slot: number) => {
        const now = zonedParts(new Date(), timezone);
        if (selectedDate < now.date) return true;
        if (selectedDate > now.date) return false;
        return slot + 60 <= timeMinutes(now.time);
    };

    const setQuickDate = (nextView: 'today' | 'tomorrow' | 'week') => {
        setView(nextView);
        setSelectedDate(nextView === 'tomorrow' ? addDays(today, 1) : today);
    };

    const openNew = (fieldId = fields[0]?.id ?? '', slot?: number) => {
        const startMinutes = slot ?? schedules.get(Number(fieldId))?.start ?? 12 * 60;
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
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { setModalOpen(false); setEditing(null); } };
        if (editing) form.put(`/reservations/${editing.id}`, options);
        else form.post('/reservations', options);
    };

    const mutate = (action: () => void) => {
        setDrawerReservation(null);
        action();
    };

    return <div className="booking-board-page">
        <header className="booking-board-heading">
            <div><span>{t('employeeWorkspace')}</span><h1>{t('bookingBoard')}</h1><p>{t('bookingBoardIntro')}</p></div>
            <Button onClick={() => openNew()} disabled={!fields.length}><Plus size={18} />{t('newReservation')}</Button>
        </header>

        <section className="booking-board-shell">
            <div className="booking-board-toolbar">
                <div className="board-view-switch" aria-label={t('dateRange')}>
                    <button className={view === 'today' ? 'active' : ''} onClick={() => setQuickDate('today')}>{t('today')}</button>
                    <button className={view === 'tomorrow' ? 'active' : ''} onClick={() => setQuickDate('tomorrow')}>{t('tomorrow')}</button>
                    <button className={view === 'week' ? 'active' : ''} onClick={() => setQuickDate('week')}>{t('week')}</button>
                </div>
                <div className="board-current-date"><CalendarDays size={17} /><strong>{formatDate(selectedDate, locale)}</strong></div>
                <button className="board-refresh" onClick={() => router.reload({ only: ['reservations', 'fields'] })} title={t('refreshBoard')} aria-label={t('refreshBoard')}><RefreshCw size={17} /></button>
            </div>

            {view === 'week' && <div className="board-week-strip">{weekDates.map(date => <button key={date} className={selectedDate === date ? 'active' : ''} onClick={() => setSelectedDate(date)}><span>{formatDate(date, locale, { weekday: 'short' }).split(' ')[0]}</span><strong>{new Date(`${date}T12:00:00Z`).getUTCDate()}</strong></button>)}</div>}

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
                                const schedule = schedules.get(field.id);
                                const reservation = reservationAt(field.id, slot);
                                const unavailable = field.status !== 'active' || !schedule || slot < schedule.start || slot + 60 > schedule.end;
                                const past = !unavailable && isPast(slot);
                                const state = unavailable ? 'maintenance' : past ? 'past' : reservation ? (reservation.status === 'pending' ? 'pending' : 'reserved') : 'available';
                                return <button key={field.id} className={`board-slot ${state} board-field-${index} ${index === mobileFieldIndex ? 'mobile-active' : ''}`} disabled={unavailable || past} onClick={() => reservation ? setDrawerReservation(reservation) : openNew(field.id, slot)}>
                                    <span className="board-slot-dot" />
                                    <span className="board-slot-copy"><strong>{reservation ? reservation.customer_name : t(state === 'maintenance' ? 'maintenance' : state === 'past' ? 'past' : 'free')}</strong><small className={reservation?.payment_status === 'paid' ? 'paid' : ''}>{reservation ? (reservation.payment_status === 'paid' ? t('paid') : t(reservation.status === 'pending' ? 'pending' : 'reserved')) : unavailable ? t('closed') : t('clickToBook')}</small></span>
                                    {reservation?.payment_status === 'paid' && <Check size={15} />}
                                </button>;
                            })}
                        </div>)}
                    </div>
                </div>
                <div className="board-legend"><span className="available">{t('available')}</span><span className="reserved">{t('reserved')}</span><span className="pending">{t('pending')}</span><span className="maintenance">{t('maintenance')}</span><span className="past">{t('past')}</span></div>
            </>}
        </section>

        <Drawer open={Boolean(drawerReservation)} title={drawerReservation?.customer_name ?? ''} subtitle={drawerReservation?.football_field.name} onClose={() => setDrawerReservation(null)} footer={drawerReservation && <>
            <Button variant="secondary" onClick={() => openEdit(drawerReservation)}><Edit3 size={16} />{t('edit')}</Button>
            {drawerReservation.payment_status !== 'paid' && <Button variant="success" onClick={() => mutate(() => router.patch(`/reservations/${drawerReservation.id}/paid`, {}, { preserveScroll: true }))}><CreditCard size={16} />{t('markPaid')}</Button>}
            {['pending', 'confirmed'].includes(drawerReservation.status) && <Button variant="secondary" onClick={() => mutate(() => router.patch(`/reservations/${drawerReservation.id}/complete`, {}, { preserveScroll: true }))}><Check size={16} />{t('markCompleted')}</Button>}
            {['pending', 'confirmed'].includes(drawerReservation.status) && <Button variant="danger" onClick={() => confirm(t('cancelReservationConfirm')) && mutate(() => router.delete(`/reservations/${drawerReservation.id}`, { preserveScroll: true }))}><Trash2 size={16} />{t('cancelReservation')}</Button>}
        </>}>
            {drawerReservation && <div className="reservation-drawer-details">
                <div><UserRound size={17} /><span>{t('customer')}</span><strong>{drawerReservation.customer_name}</strong></div>
                <a href={`tel:${drawerReservation.customer_phone}`}><Phone size={17} /><span>{t('phone')}</span><strong>{drawerReservation.customer_phone}</strong></a>
                <div><Clock3 size={17} /><span>{t('reservationTime')}</span><strong>{new Intl.DateTimeFormat(locale === 'sq' ? 'sq-AL' : 'en-GB', { timeZone: timezone, weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(drawerReservation.starts_at))}</strong></div>
                <div><CreditCard size={17} /><span>{t('payment')}</span><strong><Badge value={drawerReservation.payment_status} /></strong></div>
                <div><CalendarDays size={17} /><span>{t('status')}</span><strong><Badge value={drawerReservation.status} /></strong></div>
                <section><span>{t('notes')}</span><p>{drawerReservation.notes || t('noInternalNotes')}</p></section>
            </div>}
        </Drawer>

        <Modal open={modalOpen} title={editing ? t('editReservation') : t('newReservation')} onClose={() => setModalOpen(false)}><form onSubmit={submit} className="board-reservation-form"><div className="form-grid">
            <Field label={t('customerName')} error={form.errors.customer_name} required><Input autoFocus value={form.data.customer_name} onChange={event => form.setData('customer_name', event.target.value)} /></Field>
            <Field label={t('phone')} error={form.errors.customer_phone} required><Input inputMode="tel" value={form.data.customer_phone} onChange={event => form.setData('customer_phone', event.target.value)} /></Field>
            <Field label={t('selectField')} error={form.errors.football_field_id} required><Select value={form.data.football_field_id} onChange={event => form.setData('football_field_id', Number(event.target.value))}>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field>
            {editing && <Field label={t('status')}><Select value={form.data.status} onChange={event => form.setData('status', event.target.value)}><option value="pending">{t('pending')}</option><option value="confirmed">{t('confirmed')}</option><option value="completed">{t('completed')}</option><option value="no_show">{t('noShow')}</option></Select></Field>}
            <Field label={t('date')} error={form.errors.starts_at} required><Input type="date" value={form.data.starts_at.split('T')[0]} onChange={event => setDateOrTime('date', event.target.value)} /></Field>
            <Field label={t('time')} error={form.errors.starts_at} required><Input type="time" value={form.data.starts_at.split('T')[1] ?? ''} onChange={event => setDateOrTime('time', event.target.value)} /></Field>
            <Field label={t('duration')} error={form.errors.ends_at} required><Select value={duration} onChange={event => setDuration(Number(event.target.value))}>{[1, 2, 3, 4].map(hours => <option key={hours} value={hours}>{hours} {hours === 1 ? t('hour') : t('hours')}</option>)}</Select></Field>
            <Field label={t('payment')} error={form.errors.payment_status}><Select value={form.data.payment_status} onChange={event => form.setData('payment_status', event.target.value)}><option value="unpaid">{t('unpaid')}</option><option value="partial">{t('partial')}</option><option value="paid">{t('paid')}</option></Select></Field>
            {form.data.payment_status !== 'unpaid' && <Field label={t('amountPaid')}><Input type="number" min="0" step=".01" value={form.data.paid_amount} onChange={event => form.setData('paid_amount', Number(event.target.value))} /></Field>}
            <Field label={t('notes')} error={form.errors.notes}><textarea className="input" value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} /></Field>
            <label className="check-row"><input type="checkbox" checked={form.data.is_walk_in} onChange={event => form.setData('is_walk_in', event.target.checked)} /> {t('walkIn')}</label>
        </div>{flash.slot_suggestions && flash.slot_suggestions.length > 0 && <div className="form-callout"><strong>{t('suggestedSlots')}</strong><div className="actions">{flash.slot_suggestions.map(slot => <Button key={slot.starts_at} type="button" variant="secondary" onClick={() => form.setData(data => ({ ...data, starts_at: slot.starts_at, ends_at: slot.ends_at }))}>{slot.label}</Button>)}</div></div>}<div className="form-actions"><Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>{t('close')}</Button><Button disabled={form.processing}>{t('save')}</Button></div></form></Modal>
    </div>;
}
