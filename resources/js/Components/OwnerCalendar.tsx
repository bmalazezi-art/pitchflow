import { Head, router } from '@inertiajs/react';
import FullCalendar from '@fullcalendar/react';
import sqLocale from '@fullcalendar/core/locales/sq';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { CalendarDays, SlidersHorizontal } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { PageHeader, Select } from './UI';
import { SingleDateNavigator } from './DateControls';
import { useTodayDate } from '../hooks/useTodayDate';
import { startOfWeek } from '../lib/dateControls';
import { useLocale, useTranslation } from '../lib/i18n';
import type { CalendarProps } from '../Pages/Reservations/Calendar';

const timeMinutes = (value: string) => {
    const [hours, minutes] = value.slice(0, 5).split(':').map(Number);
    return hours * 60 + minutes;
};

const pad = (value: number) => String(value).padStart(2, '0');
const slotTime = (minutes: number) => `${pad(Math.floor(minutes / 60))}:00:00`;
const dayOfWeek = (date: string) => new Date(`${date}T12:00:00`).getDay();
const startOfMonthDate = (date: string) => new Date(`${date.slice(0, 7)}-01T12:00:00`);
const businessSlotOrder = (minutes: number) => {
    const normalized = minutes % 1440;
    return normalized === 0 ? 1440 : normalized;
};

const addDays = (date: string, days: number) => {
    const value = new Date(`${date}T12:00:00`);
    value.setDate(value.getDate() + days);
    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
};

const visibleDates = (date: string, view: 'dayGridMonth' | 'timeGridWeek' | 'timeGridDay') => {
    if (view === 'timeGridDay') return [date];
    if (view === 'timeGridWeek') {
        const firstDay = startOfWeek(date);
        return Array.from({ length: 7 }, (_, index) => addDays(firstDay, index));
    }

    const first = startOfMonthDate(date);
    return Array.from({ length: new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate() }, (_, index) => {
        const value = new Date(first);
        value.setDate(index + 1);
        return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
    });
};

const scheduleWindowFor = (field: any, date: string) => {
    const override = field.operating_hour_overrides?.find((item: any) => item.date.slice(0, 10) === date);
    const weekly = field.operating_hours?.find((item: any) => item.day_of_week === dayOfWeek(date));
    if (override?.is_closed || (!override && weekly?.is_closed)) return null;
    const opening = override?.opening_time ?? weekly?.opening_time ?? field.opening_time;
    const closing = override?.closing_time ?? weekly?.closing_time ?? field.closing_time;
    const start = timeMinutes(opening);
    let end = timeMinutes(closing);
    if (end <= start) end += 1440;

    return { start, end };
};

const calendarSlotBounds = (fields: any[], selectedDate: string, view: 'dayGridMonth' | 'timeGridWeek' | 'timeGridDay') => {
    const windows = fields.flatMap(field => visibleDates(selectedDate, view)
        .map(date => scheduleWindowFor(field, date))
        .filter(Boolean) as Array<{ start: number; end: number }>);

    if (!windows.length) return { min: 1 * 60, max: 25 * 60 };

    const starts = windows.map(window => businessSlotOrder(window.start));
    const ends = windows.map(window => window.end > 1440 ? window.end : businessSlotOrder(window.end));
    const min = Math.max(60, Math.min(...starts));
    const max = Math.max(min + 60, Math.min(25 * 60, Math.max(...ends)));

    return { min, max };
};

const reservationColor = (reservation: any, fields: any[]) => {
    const field = fields.find(item => item.id === reservation.football_field_id);
    if (field?.status === 'maintenance') return '#64748b';
    if (['cancelled', 'late_cancelled', 'no_show'].includes(reservation.status)) return '#dc2626';
    if (reservation.payment_status === 'paid') return '#16a34a';
    if (reservation.payment_status === 'partial') return '#ea580c';
    if (reservation.status === 'confirmed') return '#2563eb';
    return '#ca8a04';
};

export default function OwnerCalendar({ reservations, fields, timezone, selectedField, initialDate }: CalendarProps) {
    const t = useTranslation();
    const locale = useLocale();
    const today = useTodayDate();
    const dateWasExplicit = typeof window !== 'undefined' && (new URLSearchParams(window.location.search).has('from') || new URLSearchParams(window.location.search).has('to'));
    const [fieldFilter, setFieldFilter] = useState<number | 'all'>(selectedField ?? 'all');
    const [selectedDate, setSelectedDate] = useState(initialDate ?? today);
    const [dateManuallySelected, setDateManuallySelected] = useState(dateWasExplicit);
    const isMobileViewport = typeof window !== 'undefined' && window.innerWidth < 768;
    const [view, setView] = useState<'dayGridMonth' | 'timeGridWeek' | 'timeGridDay'>(isMobileViewport ? 'timeGridDay' : 'timeGridWeek');
    const [quickMode, setQuickMode] = useState<'today' | 'tomorrow' | 'week'>(isMobileViewport ? 'today' : 'week');
    const didMountCalendar = useRef(false);
    const calendarRef = useRef<FullCalendar | null>(null);
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
    const visibleFields = useMemo(() => fields.filter(field => fieldFilter === 'all' || field.id === fieldFilter), [fieldFilter, fields]);
    const slotBounds = useMemo(() => calendarSlotBounds(visibleFields, selectedDate, view), [selectedDate, view, visibleFields]);
    useEffect(() => {
        if (dateManuallySelected) return;
        setSelectedDate(today);
        calendarRef.current?.getApi().changeView(view, today);
    }, [dateManuallySelected, today, view]);
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
    const selectDate = (date: string, mode: 'today' | 'tomorrow' | 'week' = quickMode) => {
        const nextView = mode === 'week' ? 'timeGridWeek' : 'timeGridDay';
        setDateManuallySelected(date !== today || mode !== 'today');
        setQuickMode(mode);
        setView(nextView);
        setSelectedDate(date);
        calendarRef.current?.getApi().changeView(nextView, date);
    };
    const navigateDate = (date: string) => {
        setDateManuallySelected(date !== today || view !== 'timeGridDay');
        setSelectedDate(date);
        calendarRef.current?.getApi().changeView(view, date);
    };
    const changeView = (nextView: 'dayGridMonth' | 'timeGridWeek' | 'timeGridDay') => {
        const nextDate = nextView === 'timeGridWeek' ? startOfWeek(selectedDate) : selectedDate;
        setView(nextView);
        if (nextView === 'timeGridWeek') setQuickMode('week');
        if (nextView === 'timeGridDay') setQuickMode('today');
        setDateManuallySelected(nextDate !== today || nextView !== 'timeGridDay');
        setSelectedDate(nextDate);
        calendarRef.current?.getApi().changeView(nextView, nextDate);
    };
    const navigationUnit = view === 'dayGridMonth' ? 'month' : view === 'timeGridWeek' ? 'week' : 'day';

    return <AppLayout title={t('calendar')}><Head title={t('calendar')} /><div className="owner-page calendar-page">
        <PageHeader eyebrow={t('schedule')} title={t('calendar')} description={t('readOnlyCalendarHelp')} actions={<span className="read-only-indicator">{t('readOnly')}</span>} />
        <section className="calendar-shell read-only">
            <div className="calendar-date-panel">
                <SingleDateNavigator value={selectedDate} mode={quickMode} showWeek navigationUnit={navigationUnit} onNavigate={navigateDate} onModeChange={setQuickMode} onChange={(date, mode = quickMode) => selectDate(date, mode)} />
                <div className="calendar-view-switch" aria-label={t('calendar')}>
                    <button type="button" className={view === 'dayGridMonth' ? 'active' : ''} onClick={() => changeView('dayGridMonth')}><CalendarDays size={15} />{t('month')}</button>
                    <button type="button" className={view === 'timeGridWeek' ? 'active' : ''} onClick={() => changeView('timeGridWeek')}><CalendarDays size={15} />{t('week')}</button>
                    <button type="button" className={view === 'timeGridDay' ? 'active' : ''} onClick={() => changeView('timeGridDay')}><CalendarDays size={15} />{t('day')}</button>
                </div>
            </div>
            <div className="calendar-filterbar"><div><SlidersHorizontal size={17} /><strong>{t('fieldFilter')}</strong></div><Select aria-label={t('fieldFilter')} value={fieldFilter} onChange={event => setFieldFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))}><option value="all">{t('allFields')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select><div className="calendar-legend"><span className="paid">{t('paid')}</span><span className="confirmed">{t('confirmed')}</span><span className="partial">{t('partial')}</span><span className="problem">{t('cancelled')}</span></div></div>
            <FullCalendar ref={calendarRef} plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]} locales={[sqLocale]} locale={locale} timeZone={timezone} initialView={view} initialDate={selectedDate} headerToolbar={false} buttonText={{ today: t('today'), month: t('month'), week: t('week'), day: t('day') }} slotMinTime={slotTime(slotBounds.min)} slotMaxTime={slotTime(slotBounds.max)} slotDuration="01:00:00" slotLabelInterval="01:00:00" slotLabelContent={info => `${pad(info.date.getHours())}:${pad(info.date.getMinutes())}`} eventTimeFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }} allDaySlot={false} height="auto" nowIndicator editable={false} selectable={false} events={events} datesSet={handleDatesSet} eventDidMount={info => { info.el.title = `${info.event.title}\n${info.event.extendedProps.reservation.customer_phone}`; }} />
        </section>
    </div></AppLayout>;
}
