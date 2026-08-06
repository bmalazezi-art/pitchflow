import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Button } from './UI';
import { useLocale, useTranslation } from '../lib/i18n';
import { addDays, formatCalendarDate, formatDateLabel, formatMonthYear, shiftMonths, startOfWeek, toDateInput, weekdayLabels, type RangePeriod } from '../lib/dateControls';
import { useTodayDate } from '../hooks/useTodayDate';

type QuickDateMode = 'today' | 'tomorrow' | 'week';
type NavigationUnit = 'day' | 'week' | 'month';

const localDate = (date: string) => new Date(`${date}T12:00:00`);
const dateFromIso = (date: string) => new Date(`${date}T12:00:00`);

export function DatePicker({
    value,
    onChange,
    ariaLabel,
    showShortcuts = true,
}: {
    value: string;
    onChange: (value: string) => void;
    ariaLabel?: string;
    showShortcuts?: boolean;
}) {
    const t = useTranslation();
    const locale = useLocale();
    const today = useTodayDate();
    const selectedDate = localDate(value);
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [popoverPosition, setPopoverPosition] = useState({ top: 0, left: 0 });
    const [visibleMonth, setVisibleMonth] = useState(() => startOfMonth(selectedDate));
    const calendarDays = useMemo(() => buildCalendarDays(visibleMonth), [visibleMonth]);
    const monthLabel = formatMonthYear(visibleMonth, locale);
    const weekdays = useMemo(() => weekdayLabels(locale), [locale]);

    useEffect(() => {
        setVisibleMonth(startOfMonth(selectedDate));
    }, [value]);

    useEffect(() => {
        if (!open || !triggerRef.current) return;

        const updatePosition = () => {
            const rect = triggerRef.current?.getBoundingClientRect();
            if (!rect) return;

            const margin = 12;
            const popoverWidth = Math.min(330, window.innerWidth - margin * 2);
            const estimatedHeight = 410;
            const hasRoomBelow = window.innerHeight - rect.bottom >= estimatedHeight + margin;
            const top = hasRoomBelow ? rect.bottom + 8 : Math.max(margin, rect.top - estimatedHeight - 8);
            const left = Math.min(Math.max(margin, rect.left), Math.max(margin, window.innerWidth - popoverWidth - margin));
            setPopoverPosition({ top, left });
        };

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);

        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [open]);

    const selectDate = (date: Date) => {
        onChange(toDateInput(date));
        setVisibleMonth(startOfMonth(date));
        setOpen(false);
    };

    return <div className="calendar-picker pf-date-picker">
        <button type="button" ref={triggerRef} className="calendar-trigger pf-date-trigger" aria-label={ariaLabel ?? t('chooseDate')} aria-expanded={open} onClick={() => setOpen(isOpen => !isOpen)}>
            <CalendarDays size={19} />
            <span>{formatDateLabel(value, locale)}</span>
        </button>
        {open && <div className="calendar-popover pf-date-popover" style={{ top: popoverPosition.top, left: popoverPosition.left }} role="dialog" aria-label={ariaLabel ?? t('chooseDate')}>
            {showShortcuts && <div className="pf-date-popover-actions">
                <button type="button" onClick={() => selectDate(localDate(today))}>{t('today')}</button>
                <button type="button" onClick={() => selectDate(localDate(addDays(today, 1)))}>{t('tomorrow')}</button>
            </div>}
            <div className="calendar-month">
                <button type="button" aria-label={t('previousMonth')} onClick={() => setVisibleMonth(addMonths(visibleMonth, -1))}><ChevronLeft size={18} /></button>
                <strong>{monthLabel}</strong>
                <button type="button" aria-label={t('nextMonth')} onClick={() => setVisibleMonth(addMonths(visibleMonth, 1))}><ChevronRight size={18} /></button>
            </div>
            <div className="calendar-weekdays" aria-hidden="true">{weekdays.map(day => <span key={day}>{day}</span>)}</div>
            <div className="calendar-grid">
                {calendarDays.map(day => <button
                    type="button"
                    key={day.key}
                    className={['calendar-day', day.currentMonth ? '' : 'outside', sameDay(day.date, selectedDate) ? 'selected' : '', sameDay(day.date, localDate(today)) ? 'today' : ''].filter(Boolean).join(' ')}
                    onClick={() => selectDate(day.date)}
                >
                    {day.date.getDate()}
                </button>)}
            </div>
        </div>}
    </div>;
}

export function SingleDateNavigator({
    value,
    mode,
    onChange,
    onModeChange,
    onNavigate,
    navigationUnit,
    showWeek = false,
}: {
    value: string;
    mode?: QuickDateMode;
    onChange: (value: string, mode?: QuickDateMode) => void;
    onModeChange?: (mode: QuickDateMode) => void;
    onNavigate?: (value: string) => void;
    navigationUnit?: NavigationUnit;
    showWeek?: boolean;
}) {
    const t = useTranslation();
    const today = useTodayDate();
    const quickModes: QuickDateMode[] = showWeek ? ['today', 'tomorrow', 'week'] : ['today', 'tomorrow'];
    const currentUnit = navigationUnit ?? (mode === 'week' ? 'week' : 'day');
    const chooseMode = (nextMode: QuickDateMode) => {
        const nextDate = nextMode === 'tomorrow' ? addDays(today, 1) : nextMode === 'week' ? startOfWeek(today) : today;
        onModeChange?.(nextMode);
        onChange(nextDate, nextMode);
    };
    const move = (direction: -1 | 1) => {
        const nextDate = currentUnit === 'month'
            ? shiftMonths(value, direction, 'start')
            : currentUnit === 'week'
                ? addDays(startOfWeek(value), 7 * direction)
                : addDays(value, direction);

        if (onNavigate) {
            onNavigate(nextDate);
            return;
        }

        onChange(nextDate, mode);
    };
    const previousLabel = currentUnit === 'month' ? t('previousMonth') : currentUnit === 'week' ? t('previousWeek') : t('previousDay');
    const nextLabel = currentUnit === 'month' ? t('nextMonth') : currentUnit === 'week' ? t('nextWeek') : t('nextDay');

    return <div className="pf-period-panel pf-single-date-panel">
        <div className="pf-period-tabs">{quickModes.map(item => <button key={item} type="button" className={mode === item ? 'active' : ''} onClick={() => chooseMode(item)}>{item === 'week' ? t('thisWeek') : t(item)}</button>)}</div>
        <div className="pf-period-nav">
            <Button type="button" variant="secondary" onClick={() => move(-1)}><ChevronLeft size={16} />{previousLabel}</Button>
            <DatePicker value={value} onChange={date => onChange(date, mode)} />
            <Button type="button" variant="secondary" onClick={() => move(1)}>{nextLabel}<ChevronRight size={16} /></Button>
        </div>
    </div>;
}

export function DateRangePeriodPicker({
    period,
    from,
    to,
    onApply,
}: {
    period: RangePeriod;
    from: string;
    to: string;
    onApply: (payload: { period: RangePeriod; from?: string; to?: string }) => void;
}) {
    const t = useTranslation();
    const locale = useLocale();
    const [draftFrom, setDraftFrom] = useState(from);
    const [draftTo, setDraftTo] = useState(to);
    const [draftPeriod, setDraftPeriod] = useState<RangePeriod>(period);
    const periodOptions: RangePeriod[] = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'custom'];
    const periodLabel = (date: string) => formatCalendarDate(dateFromIso(date), locale, { day: 'numeric', month: 'short', year: 'numeric' });
    const rangeLabel = `${periodLabel(from)} - ${periodLabel(to)}`;

    useEffect(() => {
        setDraftFrom(from);
        setDraftTo(to);
        setDraftPeriod(period);
    }, [from, period, to]);

    const selectPeriod = (nextPeriod: RangePeriod) => {
        setDraftPeriod(nextPeriod);
        if (nextPeriod !== 'custom') onApply({ period: nextPeriod });
    };
    const movePeriod = (direction: -1 | 1) => {
        if (period === 'this_month') {
            onApply({ period, from: shiftMonths(from, direction, 'start'), to: shiftMonths(to, direction, 'end') });
            return;
        }
        const days = Math.max(1, Math.round((dateFromIso(to).getTime() - dateFromIso(from).getTime()) / 86400000) + 1);
        onApply({ period, from: addDays(from, days * direction), to: addDays(to, days * direction) });
    };
    const submitCustom = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onApply({ period: 'custom', from: draftFrom, to: draftTo });
    };

    return <section className="pf-period-panel">
        <div className="pf-period-tabs">{periodOptions.map(option => <button key={option} type="button" className={draftPeriod === option ? 'active' : ''} onClick={() => selectPeriod(option)}>{option === 'yesterday' ? t('periodYesterday') : t(option)}</button>)}</div>
        <div className="pf-period-nav">
            <Button type="button" variant="secondary" onClick={() => movePeriod(-1)}><ChevronLeft size={16} />{t('previousPeriod')}</Button>
            <strong>{rangeLabel}</strong>
            <Button type="button" variant="secondary" onClick={() => movePeriod(1)}>{t('nextPeriod')}<ChevronRight size={16} /></Button>
        </div>
        {draftPeriod === 'custom' && <form className="pf-date-range-form" onSubmit={submitCustom}>
            <DatePicker value={draftFrom} onChange={setDraftFrom} ariaLabel={t('start')} />
            <span>–</span>
            <DatePicker value={draftTo} onChange={setDraftTo} ariaLabel={t('end')} />
            <Button>{t('apply')}</Button>
        </form>}
    </section>;
}

function startOfMonth(date: Date) {
    return new Date(date.getFullYear(), date.getMonth(), 1, 12);
}

function addMonths(date: Date, amount: number) {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1, 12);
}

function sameDay(left: Date, right: Date) {
    return left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate();
}

function buildCalendarDays(month: Date) {
    const firstDay = startOfMonth(month);
    const mondayOffset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() - mondayOffset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);

        return { date, key: toDateInput(date), currentMonth: date.getMonth() === month.getMonth() };
    });
}
