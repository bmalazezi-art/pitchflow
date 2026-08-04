export type RangePeriod = 'today' | 'yesterday' | 'this_week' | 'last_week' | 'this_month' | 'custom';

const dateFromIso = (date: string) => new Date(`${date}T12:00:00`);
const pad = (value: number) => String(value).padStart(2, '0');
const sqMonths = ['Janar', 'Shkurt', 'Mars', 'Prill', 'Maj', 'Qershor', 'Korrik', 'Gusht', 'Shtator', 'Tetor', 'Nëntor', 'Dhjetor'];
const sqMonthsShort = ['Jan', 'Shku', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gus', 'Sht', 'Tet', 'Nën', 'Dhj'];
const sqWeekdaysFull = ['E diel', 'E hënë', 'E martë', 'E mërkurë', 'E enjte', 'E premte', 'E shtunë'];
const sqWeekdaysShort = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht'];

export const toDateInput = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
export const isoDate = (date: Date) => toDateInput(date);
export const todayIso = () => toDateInput(new Date());
export const millisecondsUntilNextLocalMidnight = (date = new Date()) => {
    const nextMidnight = new Date(date);
    nextMidnight.setHours(24, 0, 0, 0);

    return Math.max(1000, nextMidnight.getTime() - date.getTime());
};

export const addDays = (date: string, days: number) => {
    const value = dateFromIso(date);
    value.setUTCDate(value.getUTCDate() + days);
    return isoDate(value);
};

export const startOfWeek = (date: string) => {
    const value = dateFromIso(date);
    const offset = (value.getUTCDay() + 6) % 7;
    value.setUTCDate(value.getUTCDate() - offset);
    return isoDate(value);
};

export const shiftMonths = (date: string, months: number, edge: 'start' | 'end') => {
    const value = dateFromIso(date);
    value.setUTCMonth(value.getUTCMonth() + months, 1);
    if (edge === 'end') value.setUTCMonth(value.getUTCMonth() + 1, 0);
    return isoDate(value);
};

export const localeCode = (locale: string) => locale === 'sq' ? 'sq-AL' : 'en-GB';

export function formatDateLabel(date: string, locale = 'en', options: Intl.DateTimeFormatOptions = {}) {
    return formatCalendarDate(new Date(`${date}T12:00:00`), locale, {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        ...options,
    });
}

export function formatCalendarDate(date: Date, locale = 'en', options: Intl.DateTimeFormatOptions = {}) {
    if (locale !== 'sq') {
        return new Intl.DateTimeFormat(localeCode(locale), options).format(date);
    }

    const parts: string[] = [];
    if (options.weekday) {
        parts.push(options.weekday === 'short' ? sqWeekdaysShort[date.getDay()] : sqWeekdaysFull[date.getDay()]);
    }

    const body: string[] = [];
    if (options.day) {
        const day = options.day === '2-digit' ? pad(date.getDate()) : String(date.getDate());
        body.push(day);
    }
    if (options.month) {
        body.push(options.month === 'short' ? sqMonthsShort[date.getMonth()] : sqMonths[date.getMonth()]);
    }
    if (options.year) {
        body.push(String(date.getFullYear()));
    }

    return parts.length && body.length ? `${parts.join(' ')}, ${body.join(' ')}` : [...parts, ...body].join(' ');
}

export function formatMonthYear(date: Date, locale = 'en') {
    if (locale === 'sq') {
        return `${sqMonths[date.getMonth()]} ${date.getFullYear()}`;
    }

    return new Intl.DateTimeFormat(localeCode(locale), { month: 'long', year: 'numeric' }).format(date);
}

export function weekdayLabels(locale = 'en') {
    if (locale === 'sq') {
        return ['Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht', 'Die'];
    }

    const monday = new Date(2024, 0, 1, 12);

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(monday);
        date.setDate(monday.getDate() + index);
        return new Intl.DateTimeFormat(localeCode(locale), { weekday: 'short' }).format(date);
    });
}
