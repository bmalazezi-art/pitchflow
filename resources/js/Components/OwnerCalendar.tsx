import { Head, router } from '@inertiajs/react';
import FullCalendar from '@fullcalendar/react';
import sqLocale from '@fullcalendar/core/locales/sq';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { SlidersHorizontal } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { PageHeader, Select } from './UI';
import { useTranslation } from '../lib/i18n';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '../types';
import type { CalendarProps } from '../Pages/Reservations/Calendar';

const reservationColor = (reservation: any, fields: any[]) => {
    const field = fields.find(item => item.id === reservation.football_field_id);
    if (field?.status === 'maintenance') return '#64748b';
    if (['cancelled', 'late_cancelled', 'no_show'].includes(reservation.status)) return '#dc2626';
    if (reservation.payment_status === 'paid') return '#16a34a';
    if (reservation.payment_status === 'partial') return '#ea580c';
    if (reservation.status === 'confirmed') return '#2563eb';
    return '#ca8a04';
};

export default function OwnerCalendar({ reservations, fields, selectedField }: CalendarProps) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const [fieldFilter, setFieldFilter] = useState<number | 'all'>(selectedField ?? 'all');
    const didMountCalendar = useRef(false);
    const events = useMemo(() => reservations
        .filter(reservation => fieldFilter === 'all' || reservation.football_field_id === fieldFilter)
        .map(reservation => ({
            id: String(reservation.id),
            title: `${reservation.customer_name} · ${reservation.football_field.name}`,
            start: reservation.starts_at,
            end: reservation.ends_at,
            backgroundColor: reservationColor(reservation, fields),
            borderColor: reservationColor(reservation, fields),
            extendedProps: { reservation },
        })), [fieldFilter, fields, reservations]);
    const handleDatesSet = useCallback((info: { startStr: string; endStr: string }) => {
        if (!didMountCalendar.current) {
            didMountCalendar.current = true;
            return;
        }

        router.get('/calendar', {
            from: info.startStr.slice(0, 10),
            to: info.endStr.slice(0, 10),
            field: fieldFilter === 'all' ? undefined : fieldFilter,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['reservations', 'fields', 'selectedField'],
        });
    }, [fieldFilter]);

    return <AppLayout title={t('calendar')}><Head title={t('calendar')} /><div className="owner-page calendar-page">
        <PageHeader eyebrow={t('schedule')} title={t('calendar')} description={t('readOnlyCalendarHelp')} actions={<span className="read-only-indicator">{t('readOnly')}</span>} />
        <section className="calendar-shell read-only">
            <div className="calendar-filterbar"><div><SlidersHorizontal size={17} /><strong>{t('fieldFilter')}</strong></div><Select aria-label={t('fieldFilter')} value={fieldFilter} onChange={event => setFieldFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))}><option value="all">{t('allFields')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select><div className="calendar-legend"><span className="paid">{t('paid')}</span><span className="confirmed">{t('confirmed')}</span><span className="partial">{t('partial')}</span><span className="problem">{t('cancelled')}</span></div></div>
            <FullCalendar plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]} locales={[sqLocale]} locale={locale} initialView={window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek'} headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }} buttonText={{ today: t('today'), month: t('month'), week: t('week'), day: t('day') }} slotMinTime="12:00:00" slotMaxTime="26:00:00" slotDuration="01:00:00" slotLabelInterval="01:00:00" allDaySlot={false} height="auto" nowIndicator editable={false} selectable={false} events={events} datesSet={handleDatesSet} eventDidMount={info => { info.el.title = `${info.event.title}\n${info.event.extendedProps.reservation.customer_phone}`; }} />
        </section>
    </div></AppLayout>;
}
