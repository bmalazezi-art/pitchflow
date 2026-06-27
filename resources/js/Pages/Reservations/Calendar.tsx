import { Head, router, useForm, usePage } from '@inertiajs/react';
import FullCalendar from '@fullcalendar/react';
import sqLocale from '@fullcalendar/core/locales/sq';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin, { DateClickArg } from '@fullcalendar/interaction';
import type { EventDropArg } from '@fullcalendar/core';
import { CalendarPlus, SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Field, Input, Modal, PageHeader, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

const reservationColor = (reservation: any, fields: any[]) => {
    const field = fields.find(item => item.id === reservation.football_field_id);
    if (field?.status === 'maintenance') return '#64748b';
    if (['cancelled', 'late_cancelled', 'no_show'].includes(reservation.status)) return '#dc2626';
    if (reservation.payment_status === 'paid') return '#16a34a';
    if (reservation.payment_status === 'partial') return '#ea580c';
    if (reservation.status === 'confirmed') return '#2563eb';
    return '#ca8a04';
};
const localInput = (date: Date | string) => { const d = new Date(date); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };

export default function Calendar({ reservations, fields, selectedField, selectedReservation }: { reservations: any[]; fields: any[]; timezone: string; selectedField?: number | null; selectedReservation?: number | null }) {
    const t = useTranslation();
    const initialReservation = selectedReservation ? reservations.find(reservation => reservation.id === selectedReservation) : null;
    const [open, setOpen] = useState(Boolean(initialReservation));
    const [editing, setEditing] = useState<any>(initialReservation ?? null);
    const [fieldFilter, setFieldFilter] = useState<number | 'all'>(selectedField ?? initialReservation?.football_field_id ?? 'all');
    const { auth, locale, flash } = usePage<SharedProps>().props;
    const canManageReservations = auth.user?.role === 'employee';
    const form = useForm(initialReservation ? { customer_name: initialReservation.customer_name, customer_phone: initialReservation.customer_phone, football_field_id: initialReservation.football_field_id, starts_at: localInput(initialReservation.starts_at), ends_at: localInput(initialReservation.ends_at), status: initialReservation.status, payment_status: initialReservation.payment_status, paid_amount: Number(initialReservation.paid_amount), is_walk_in: initialReservation.is_walk_in, notes: initialReservation.notes ?? '' } : { customer_name: '', customer_phone: '', football_field_id: selectedField ?? fields[0]?.id ?? '', starts_at: '', ends_at: '', status: 'confirmed', payment_status: 'unpaid', paid_amount: 0, is_walk_in: false, notes: '' });
    const showNew = (start = new Date()) => { const end = new Date(start); end.setHours(end.getHours() + 1); setEditing(null); form.setData({ customer_name: '', customer_phone: '', football_field_id: fieldFilter === 'all' ? fields[0]?.id ?? '' : fieldFilter, starts_at: localInput(start), ends_at: localInput(end), status: 'confirmed', payment_status: 'unpaid', paid_amount: 0, is_walk_in: false, notes: '' }); setOpen(true); };
    const showEdit = (reservation: any) => { setEditing(reservation); form.setData({ customer_name: reservation.customer_name, customer_phone: reservation.customer_phone, football_field_id: reservation.football_field_id, starts_at: localInput(reservation.starts_at), ends_at: localInput(reservation.ends_at), status: reservation.status, payment_status: reservation.payment_status, paid_amount: Number(reservation.paid_amount), is_walk_in: reservation.is_walk_in, notes: reservation.notes ?? '' }); setOpen(true); };
    const submit = (event: React.FormEvent) => { event.preventDefault(); if (editing) form.put(`/reservations/${editing.id}`, { onSuccess: () => setOpen(false) }); else form.post('/reservations', { onSuccess: () => setOpen(false) }); };
    const drop = (arg: EventDropArg) => { const reservation = reservations.find(item => String(item.id) === arg.event.id); if (!reservation || !arg.event.start || !arg.event.end) return arg.revert(); router.put(`/reservations/${reservation.id}`, { ...reservation, football_field_id: reservation.football_field_id, starts_at: localInput(arg.event.start), ends_at: localInput(arg.event.end) }, { preserveScroll: true, onError: () => arg.revert() }); };
    const events = useMemo(() => reservations
        .filter(reservation => fieldFilter === 'all' || reservation.football_field_id === fieldFilter)
        .map(reservation => ({ id: String(reservation.id), title: `${reservation.customer_name} · ${reservation.football_field.name}`, start: reservation.starts_at, end: reservation.ends_at, backgroundColor: reservationColor(reservation, fields), borderColor: reservationColor(reservation, fields), extendedProps: { reservation } })), [fieldFilter, fields, reservations]);

    return <AppLayout title={t('calendar')}><Head title={t('calendar')} /><div className="owner-page calendar-page">
        <PageHeader eyebrow={t('schedule')} title={t('calendar')} description={canManageReservations ? t('calendarHelp') : t('readOnlyCalendarHelp')} actions={canManageReservations ? <Button onClick={() => showNew()}><CalendarPlus size={18} />{t('newReservation')}</Button> : <span className="read-only-indicator">{t('readOnly')}</span>} />
        <section className={`calendar-shell ${canManageReservations ? '' : 'read-only'}`}>
            <div className="calendar-filterbar"><div><SlidersHorizontal size={17} /><strong>{t('fieldFilter')}</strong></div><Select aria-label={t('fieldFilter')} value={fieldFilter} onChange={event => setFieldFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))}><option value="all">{t('allFields')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select><div className="calendar-legend"><span className="paid">{t('paid')}</span><span className="confirmed">{t('confirmed')}</span><span className="partial">{t('partial')}</span><span className="problem">{t('cancelled')}</span></div></div>
            <FullCalendar plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]} locales={[sqLocale]} locale={locale} initialView={window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek'} headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }} buttonText={{ today: t('today'), month: t('month'), week: t('week'), day: t('day') }} slotMinTime="12:00:00" slotMaxTime="26:00:00" slotDuration="01:00:00" slotLabelInterval="01:00:00" allDaySlot={false} height="auto" nowIndicator editable={canManageReservations} selectable={canManageReservations} dateClick={canManageReservations ? (arg: DateClickArg) => showNew(arg.date) : undefined} eventDrop={drop} events={events} eventClick={canManageReservations ? info => showEdit(info.event.extendedProps.reservation) : undefined} eventDidMount={info => { info.el.title = `${info.event.title}\n${info.event.extendedProps.reservation.customer_phone}`; }} />
        </section>
        <Modal open={open} title={editing ? t('editReservation') : t('newReservation')} onClose={() => setOpen(false)}><form onSubmit={submit}><div className="form-grid">
            <Field label={t('customerName')} error={form.errors.customer_name} required><Input autoFocus value={form.data.customer_name} onChange={event => form.setData('customer_name', event.target.value)} /></Field><Field label={t('phone')} error={form.errors.customer_phone} required><Input value={form.data.customer_phone} onChange={event => form.setData('customer_phone', event.target.value)} /></Field>
            <Field label={t('selectField')} error={form.errors.football_field_id} required><Select value={form.data.football_field_id} onChange={event => form.setData('football_field_id', Number(event.target.value))}>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field>{editing && <Field label={t('status')}><Select value={form.data.status} onChange={event => form.setData('status', event.target.value)}><option value="pending">{t('pending')}</option><option value="confirmed">{t('confirmed')}</option><option value="completed">{t('completed')}</option><option value="no_show">{t('noShow')}</option></Select></Field>}
            <Field label={t('start')} error={form.errors.starts_at} required><Input type="datetime-local" value={form.data.starts_at} onChange={event => form.setData('starts_at', event.target.value)} /></Field><Field label={t('end')} error={form.errors.ends_at} required><Input type="datetime-local" value={form.data.ends_at} onChange={event => form.setData('ends_at', event.target.value)} /></Field>
            <Field label={t('payment')} error={form.errors.payment_status}><Select value={form.data.payment_status} onChange={event => form.setData('payment_status', event.target.value)}><option value="unpaid">{t('unpaid')}</option><option value="partial">{t('partial')}</option><option value="paid">{t('paid')}</option></Select></Field>{form.data.payment_status !== 'unpaid' && <Field label={t('amountPaid')}><Input type="number" min="0" step=".01" value={form.data.paid_amount} onChange={event => form.setData('paid_amount', Number(event.target.value))} /></Field>}
            <Field label={t('notes')} error={form.errors.notes}><textarea className="input" value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} /></Field><label className="check-row"><input type="checkbox" checked={form.data.is_walk_in} onChange={event => form.setData('is_walk_in', event.target.checked)} /> {t('walkIn')}</label>
        </div>{flash.slot_suggestions && flash.slot_suggestions.length > 0 && <div className="form-callout"><strong>{t('suggestedSlots')}</strong><div className="actions">{flash.slot_suggestions.map(slot => <Button key={slot.starts_at} type="button" variant="secondary" onClick={() => { form.setData('starts_at', slot.starts_at); form.setData('ends_at', slot.ends_at); }}>{slot.label}</Button>)}</div></div>}{editing?.customer && <div className="form-callout"><strong>{t('customerHistory')}</strong><p>{editing.customer.total_reservations} {t('reservations').toLowerCase()} · {editing.customer.no_shows} {t('noShows').toLowerCase()} · <Badge value={editing.customer.reliability_status} /></p></div>}<div className="form-actions">{editing && <><Button type="button" variant="danger" onClick={() => confirm(t('cancelReservationConfirm')) && router.delete(`/reservations/${editing.id}`, { onSuccess: () => setOpen(false) })}>{t('cancelReservation')}</Button>{editing.status === 'confirmed' && <Button type="button" variant="success" onClick={() => router.patch(`/reservations/${editing.id}/complete`, {}, { onSuccess: () => setOpen(false) })}>{t('markCompleted')}</Button>}</>}<Button type="button" variant="secondary" onClick={() => setOpen(false)}>{t('close')}</Button><Button disabled={form.processing}>{t('save')}</Button></div></form></Modal>
    </div></AppLayout>;
}
