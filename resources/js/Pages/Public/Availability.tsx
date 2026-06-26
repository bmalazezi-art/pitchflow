import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Coffee, Languages, MapPin, Moon, ParkingCircle, Phone, Search, ShowerHead, Sun, Trophy } from 'lucide-react';
import { Button } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

interface PublicField {
    id: number;
    name: string;
    address?: string | null;
    price_per_hour?: string | number | null;
    city?: { id: number; name: string } | null;
}

interface PublicBusiness {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    number_of_fields?: number | null;
    currency?: string | null;
    city?: { id: number; name: string } | null;
    football_fields?: PublicField[];
}

interface Props {
    cities: Array<{ id: number; name: string }>;
    businesses: PublicBusiness[];
    selectedBusiness?: PublicBusiness | null;
    pitchAvailability: Array<{
        field: PublicField;
        slots: Array<{ starts_at: string; ends_at: string; label: string; status: string }>;
    }>;
    filters: { city?: number | null; business?: number | null; date: string };
}

type Amenity = 'parking' | 'cafe' | 'showers';

const demoAmenities: Record<string, Amenity[]> = {
    'Getoari Sport Center': ['parking', 'cafe'],
    'Arena Sport': ['parking', 'showers'],
    'Andrra Sport Center': ['cafe', 'showers'],
    'Rilindja Football Center': ['parking', 'cafe', 'showers'],
    'Princi Football Arena': ['parking'],
    'Green Sport Arena': ['parking', 'cafe'],
    'Arena 7': ['cafe', 'showers'],
    'Demo Football Center': ['parking', 'cafe', 'showers'],
};

const venueImages = [
    'linear-gradient(135deg, #e0f2fe 0%, #dcfce7 100%)',
    'linear-gradient(135deg, #dbeafe 0%, #ccfbf1 100%)',
    'linear-gradient(135deg, #f0fdf4 0%, #e0e7ff 100%)',
    'linear-gradient(135deg, #ecfeff 0%, #f7fee7 100%)',
];

export default function Availability({ cities, businesses, selectedBusiness, pitchAvailability, filters }: Props) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const selectedCity = cities.find(city => city.id === Number(filters.city));
    const hasCity = Boolean(filters.city);
    const hasBusiness = Boolean(selectedBusiness);
    const [dark, setDark] = useState(() => localStorage.getItem('public-theme') === 'dark');

    useEffect(() => {
        localStorage.setItem('public-theme', dark ? 'dark' : 'light');
    }, [dark]);

    const setLocale = (nextLocale: 'en' | 'sq') => router.post('/locale', { locale: nextLocale }, { preserveScroll: true });
    const navigate = (overrides: Partial<Props['filters']>) => router.get('/', {
        city: overrides.city ?? filters.city ?? undefined,
        date: overrides.date ?? filters.date,
        business: overrides.business ?? filters.business ?? undefined,
    }, { preserveState: true, preserveScroll: true });
    const setCity = (value: string) => router.get('/', { city: value || undefined, date: filters.date }, { preserveState: true, preserveScroll: true, replace: true });
    const setDate = (date: string) => navigate({ date });
    const viewBusiness = (businessId: number) => navigate({ business: businessId });

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={t('checkAvailabilityTitle')} />
        <PublicNav locale={locale} dark={dark} setLocale={setLocale} setDark={setDark} />
        <main className="public-home">
            <section className="public-hero light">
                <div className="hero-content">
                    <span className="hero-kicker"><Trophy size={16} /> {t('verifiedFields')}</span>
                    <h1>{t('checkAvailabilityTitle')}</h1>
                    <p>{t('availabilityHeroDescription')}</p>
                    <SearchPanel cities={cities} filters={filters} setCity={setCity} setDate={setDate} />
                </div>
            </section>

            {hasCity && !hasBusiness && <section className="field-discovery" aria-labelledby="discover-fields">
                <div className="section-heading">
                    <div>
                        <span>{selectedCity?.name ?? t('chooseCity')}</span>
                        <h2 id="discover-fields">{t('discoverFootballFields')}</h2>
                    </div>
                    <p>{t('discoverFootballFieldsIntro')}</p>
                </div>
                <div className="field-card-grid">
                    {businesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewBusiness(business.id)} />)}
                </div>
                {businesses.length === 0 && <div className="public-empty"><Search size={24} /><p>{t('selectCityToDiscover')}</p></div>}
            </section>}

            {hasBusiness && selectedBusiness && <AvailabilitySection business={selectedBusiness} date={filters.date} pitchAvailability={pitchAvailability} />}
        </main>
    </div>;
}

function PublicNav({ locale, dark, setLocale, setDark }: {
    locale: 'en' | 'sq';
    dark: boolean;
    setLocale: (locale: 'en' | 'sq') => void;
    setDark: (dark: boolean) => void;
}) {
    const t = useTranslation();
    return <nav className="public-nav">
        <div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div>
        <div className="public-nav-actions">
            <div className="language-switcher" aria-label={t('language')}>
                <Languages size={16} />
                <button className={locale === 'en' ? 'active' : ''} onClick={() => setLocale('en')}>EN</button>
                <button className={locale === 'sq' ? 'active' : ''} onClick={() => setLocale('sq')}>SQ</button>
            </div>
            <button className="public-theme-toggle" onClick={() => setDark(!dark)} title={dark ? 'Light mode' : 'Dark mode'} aria-label={dark ? 'Light mode' : 'Dark mode'}>
                {dark ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <Link href="/login" className="btn btn-secondary">{t('login')}</Link>
            <Link href="/register" className="btn btn-primary"><span className="desktop-label">{t('register')}</span><span className="mobile-label">{t('registerShort')}</span></Link>
        </div>
    </nav>;
}

function SearchPanel({ cities, filters, setCity, setDate }: {
    cities: Props['cities'];
    filters: Props['filters'];
    setCity: (value: string) => void;
    setDate: (value: string) => void;
}) {
    const t = useTranslation();
    return <div className="availability-search-card simple">
        <label className="public-input">
            <span><MapPin size={18} /></span>
            <select aria-label={t('selectCity')} value={filters.city ?? ''} onChange={event => setCity(event.target.value)} required>
                <option value="">{t('selectCity')}</option>
                {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
            </select>
        </label>
        <CalendarPicker value={filters.date} onChange={setDate} />
    </div>;
}

function CalendarPicker({ value, onChange }: { value: string; onChange: (value: string) => void }) {
    const t = useTranslation();
    const selectedDate = parseDate(value);
    const [open, setOpen] = useState(false);
    const [visibleMonth, setVisibleMonth] = useState(() => startOfMonth(selectedDate));
    const calendarDays = useMemo(() => buildCalendarDays(visibleMonth), [visibleMonth]);
    const monthLabel = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(visibleMonth);
    const weekdays = useMemo(() => buildWeekdayLabels(), []);

    const selectDate = (date: Date) => {
        onChange(toDateInput(date));
        setVisibleMonth(startOfMonth(date));
        setOpen(false);
    };

    return <div className="calendar-picker">
        <button
            type="button"
            className="calendar-trigger"
            aria-label={t('chooseDate')}
            aria-expanded={open}
            onClick={() => setOpen(isOpen => !isOpen)}
        >
            <CalendarDays size={19} />
            <span>{formatDate(value)}</span>
        </button>
        {open && <div className="calendar-popover" role="dialog" aria-label={t('chooseDate')}>
            <div className="calendar-month">
                <button type="button" aria-label={t('previousMonth')} onClick={() => setVisibleMonth(addMonths(visibleMonth, -1))}>
                    <ChevronLeft size={18} />
                </button>
                <strong>{monthLabel}</strong>
                <button type="button" aria-label={t('nextMonth')} onClick={() => setVisibleMonth(addMonths(visibleMonth, 1))}>
                    <ChevronRight size={18} />
                </button>
            </div>
            <div className="calendar-weekdays" aria-hidden="true">
                {weekdays.map(day => <span key={day}>{day}</span>)}
            </div>
            <div className="calendar-grid">
                {calendarDays.map(day => <button
                    type="button"
                    key={day.key}
                    className={[
                        'calendar-day',
                        day.currentMonth ? '' : 'outside',
                        sameDay(day.date, selectedDate) ? 'selected' : '',
                        sameDay(day.date, parseDate(today())) ? 'today' : '',
                    ].filter(Boolean).join(' ')}
                    onClick={() => selectDate(day.date)}
                >
                    {day.date.getDate()}
                </button>)}
            </div>
        </div>}
    </div>;
}

function BusinessCard({ business, index, onView }: { business: PublicBusiness; index: number; onView: () => void }) {
    const t = useTranslation();
    const amenities = demoAmenities[business.name] ?? ['parking'];
    const price = priceSummary(business.football_fields ?? [], business.currency ?? 'EUR');
    return <article className="field-discovery-card">
        <div className="venue-cover light-cover" style={{ background: venueImages[index % venueImages.length] }}>
            <div className="venue-cover-lines" />
        </div>
        <div className="venue-card-body">
            <div>
                <h3>{business.name}</h3>
                <p><MapPin size={15} /> {business.city?.name}</p>
            </div>
            {business.address && <p><MapPin size={15} /> {business.address}</p>}
            {business.phone && <a href={`tel:${business.phone.replaceAll(' ', '')}`}><Phone size={15} /> {business.phone}</a>}
            <p><Trophy size={15} /> {pitchLabel(t, business.number_of_fields ?? business.football_fields?.length ?? 1)}</p>
            {price && <p className="venue-price"><span>{t('pricePerHour')}</span><strong>{price}</strong></p>}
            <AmenityList amenities={amenities} />
            <Button className="card-cta" onClick={onView}>{t('viewAvailability')} <ChevronRight size={15} /></Button>
        </div>
    </article>;
}

function AvailabilitySection({ business, date, pitchAvailability }: {
    business: PublicBusiness;
    date: string;
    pitchAvailability: Props['pitchAvailability'];
}) {
    const t = useTranslation();
    return <section className="availability-results focused" aria-labelledby="availability-results-heading">
        <div className="availability-header">
            <div>
                <h2 id="availability-results-heading">{business.name} — {business.city?.name}</h2>
                <p>{formatDate(date)}</p>
            </div>
            {business.phone && <a href={`tel:${business.phone.replaceAll(' ', '')}`}><Phone size={16} /> {business.phone}</a>}
        </div>
        <div className="pitch-availability-list">
            {pitchAvailability.map((pitch, index) => <section className="pitch-slots" key={pitch.field.id}>
                <div className="pitch-slots-header">
                    <h3>{pitch.field.name || `${t('footballPitch')} ${index + 1}`}</h3>
                    {pitch.field.price_per_hour !== undefined && pitch.field.price_per_hour !== null && <span>{formatMoney(pitch.field.price_per_hour, business.currency ?? 'EUR')} / h</span>}
                </div>
                <div className="slot-card-grid compact">
                    {pitch.slots.map(slot => <TimeSlotCard key={slot.starts_at} slot={slot} />)}
                </div>
            </section>)}
        </div>
    </section>;
}

function TimeSlotCard({ slot }: { slot: Props['pitchAvailability'][number]['slots'][number] }) {
    const t = useTranslation();
    return <div className={`time-slot-card compact ${slot.status}`}>
        <strong>{slot.label}</strong>
        <span>{slot.status === 'available' ? t('available') : slot.status === 'occupied' ? t('occupied') : t('past')}</span>
    </div>;
}

function AmenityList({ amenities }: { amenities: Amenity[] }) {
    const t = useTranslation();
    const icons = { parking: ParkingCircle, cafe: Coffee, showers: ShowerHead };
    return <div className="amenity-list">
        {amenities.map(amenity => {
            const Icon = icons[amenity];
            return <span key={amenity}><Icon size={14} /> {t(amenity)}</span>;
        })}
    </div>;
}

function pitchLabel(t: ReturnType<typeof useTranslation>, count: number) {
    return `${count} ${count === 1 ? t('footballPitch') : t('footballPitches')}`;
}

function priceSummary(fields: PublicField[], currency: string) {
    const prices = fields
        .map(field => Number(field.price_per_hour))
        .filter(price => Number.isFinite(price));

    if (prices.length === 0) {
        return null;
    }

    const min = Math.min(...prices);
    const max = Math.max(...prices);

    if (min === max) {
        return `${formatMoney(min, currency)} / h`;
    }

    return `${formatMoney(min, currency)}–${formatMoney(max, currency)} / h`;
}

function formatMoney(value: string | number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: Number(value) % 1 === 0 ? 0 : 2,
    }).format(Number(value));
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function parseDate(date: string) {
    return new Date(`${date}T12:00:00`);
}

function startOfMonth(date: Date) {
    return new Date(date.getFullYear(), date.getMonth(), 1, 12);
}

function addMonths(date: Date, amount: number) {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1, 12);
}

function toDateInput(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function sameDay(left: Date, right: Date) {
    return left.getFullYear() === right.getFullYear()
        && left.getMonth() === right.getMonth()
        && left.getDate() === right.getDate();
}

function buildCalendarDays(month: Date) {
    const firstDay = startOfMonth(month);
    const mondayOffset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() - mondayOffset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);

        return {
            date,
            key: toDateInput(date),
            currentMonth: date.getMonth() === month.getMonth(),
        };
    });
}

function buildWeekdayLabels() {
    const monday = new Date(2024, 0, 1, 12);

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(monday);
        date.setDate(monday.getDate() + index);

        return new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(date);
    });
}

function formatDate(date: string) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${date}T12:00:00`));
}
