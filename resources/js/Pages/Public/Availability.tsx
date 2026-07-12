import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { BadgeCheck, Building2, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock3, Coffee, Facebook, Instagram, Languages, Lightbulb, Linkedin, MapPin, MapPinned, Moon, ParkingCircle, Phone, Search, ShieldCheck, ShowerHead, Sparkles, Sun, TreePine, Trophy, Warehouse, Zap } from 'lucide-react';
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
    amenities?: Amenity[] | null;
    is_new?: boolean;
    is_verified?: boolean;
    operating_status?: { is_open: boolean; opens_at?: string | null };
}

interface Props {
    cities: Array<{ id: number; name: string }>;
    businesses: PublicBusiness[];
    recentBusinesses: PublicBusiness[];
    popularCities: Array<{ id: number; name: string; football_fields_count: number | string }>;
    statistics: { football_fields: number; cities: number; registered_businesses: number; verified_businesses: number };
    selectedBusiness?: PublicBusiness | null;
    pitchAvailability: Array<{
        field: PublicField;
        slots: Array<{ starts_at: string; ends_at: string; label: string; status: string }>;
    }>;
    filters: { city?: number | null; business?: number | null; date: string };
}

type Amenity = 'parking' | 'cafe' | 'showers' | 'indoor' | 'outdoor' | 'lighting';
const supportedAmenities: Amenity[] = ['parking', 'cafe', 'showers', 'indoor', 'outdoor', 'lighting'];

const venueImages = [
    'linear-gradient(135deg, #e0f2fe 0%, #dcfce7 100%)',
    'linear-gradient(135deg, #dbeafe 0%, #ccfbf1 100%)',
    'linear-gradient(135deg, #f0fdf4 0%, #e0e7ff 100%)',
    'linear-gradient(135deg, #ecfeff 0%, #f7fee7 100%)',
];

export default function Availability({ cities, businesses, recentBusinesses, statistics, selectedBusiness, pitchAvailability, filters }: Props) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const selectedCity = cities.find(city => city.id === Number(filters.city));
    const [draftCity, setDraftCity] = useState(filters.city ? String(filters.city) : '');
    const [draftDate, setDraftDate] = useState(filters.date);
    const hasCity = Boolean(filters.city);
    const hasBusiness = Boolean(selectedBusiness);
    const hasSearch = hasCity || hasBusiness;
    const searchIsDirty = draftCity !== (filters.city ? String(filters.city) : '') || draftDate !== filters.date;
    const showSearchResults = hasCity && !hasBusiness && !searchIsDirty;
    const [dark, setDark] = useState(() => localStorage.getItem('public-theme') === 'dark');
    const discoveryTitle = selectedCity ? `${t('footballFieldsIn')} ${selectedCity.name}` : t('discoverFootballFields');
    const discoverySummary = selectedCity
        ? `${businesses.length} ${businesses.length === 1 ? t('footballFieldFound') : t('footballFieldsFound')} ${t('inCity')} ${selectedCity.name}`
        : t('discoverFootballFieldsIntro');
    useEffect(() => {
        localStorage.setItem('public-theme', dark ? 'dark' : 'light');
    }, [dark]);

    useEffect(() => {
        setDraftCity(filters.city ? String(filters.city) : '');
        setDraftDate(filters.date);
    }, [filters.city, filters.date]);

    const setLocale = (nextLocale: 'en' | 'sq') => router.post('/locale', { locale: nextLocale }, { preserveScroll: true });
    const navigate = (overrides: Partial<Props['filters']>) => router.get('/', {
        city: overrides.city ?? filters.city ?? undefined,
        date: overrides.date ?? filters.date,
        business: overrides.business ?? filters.business ?? undefined,
    }, { preserveState: true, preserveScroll: true });
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!draftCity) {
            return;
        }

        router.get('/', { city: draftCity, date: draftDate }, {
            preserveState: true,
            preserveScroll: false,
        });
    };
    const viewBusiness = (businessId: number) => navigate({ business: businessId });
    const viewRecentBusiness = (business: PublicBusiness) => router.get('/', { city: business.city?.id, business: business.id, date: draftDate });

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={t('checkAvailabilityTitle')} />
        <PublicNav locale={locale} dark={dark} setLocale={setLocale} setDark={setDark} />
        <main className="public-home">
            <section className="public-hero light">
                <div className="hero-content">
                    <span className="hero-kicker"><Trophy size={16} /> {t('verifiedFields')}</span>
                    <h1>{t('checkAvailabilityTitle')}</h1>
                    <p>{t('availabilityHeroDescription')}</p>
                    <SearchPanel cities={cities} city={draftCity} date={draftDate} setCity={setDraftCity} setDate={setDraftDate} onSubmit={submitSearch} />
                    <div className="availability-only-note"><ShieldCheck size={17} /><span><strong>{t('availabilityOnly')}</strong>{t('reservationsDirect')}</span></div>
                </div>
            </section>

            {!hasBusiness && !hasSearch && <>
                <PlatformStatistics statistics={statistics} />
                {recentBusinesses.length > 0 && <section className="public-content-section recent-fields" aria-labelledby="recently-added-heading">
                    <PublicSectionHeading eyebrow={t('latestOnPitchFlow')} title={t('recentlyAdded')} description={t('recentlyAddedIntro')} id="recently-added-heading" />
                    <div className="field-card-grid">
                        {recentBusinesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewRecentBusiness(business)} />)}
                    </div>
                </section>}
            </>}

            {showSearchResults && <section className="field-discovery" aria-labelledby="discover-fields">
                <div className="section-heading">
                    <div>
                        <span>{selectedCity?.name ?? t('chooseCity')}</span>
                        <h2 id="discover-fields">{discoveryTitle}</h2>
                    </div>
                    <p>{discoverySummary}</p>
                </div>
                <div className="field-card-grid">
                    {businesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewBusiness(business.id)} />)}
                </div>
                {businesses.length === 0 && <div className="public-empty"><Search size={24} /><p>{t('noFootballFieldsInCity')}</p></div>}
            </section>}

            {hasCity && !hasBusiness && searchIsDirty && <section className="field-discovery" aria-labelledby="pending-search-heading">
                <div className="public-empty">
                    <Search size={24} />
                    <h2 id="pending-search-heading">{t('searchReadyTitle')}</h2>
                    <p>{t('searchReadyText')}</p>
                </div>
            </section>}

            {hasBusiness && selectedBusiness && <AvailabilitySection business={selectedBusiness} date={filters.date} pitchAvailability={pitchAvailability} />}

            {!hasBusiness && !hasSearch && <>
                <WhyPitchFlow />
                <PartnerCallout />
                <FrequentlyAskedQuestions />
            </>}
        </main>
        {!hasBusiness && <PublicFooter />}
    </div>;
}

function PlatformStatistics({ statistics }: { statistics: Props['statistics'] }) {
    const t = useTranslation();
    const cards = [
        { label: t('footballFieldsStat'), value: statistics.football_fields, icon: Trophy, tone: 'green' },
        { label: t('citiesStat'), value: statistics.cities, icon: MapPinned, tone: 'blue' },
        { label: t('registeredBusinesses'), value: statistics.registered_businesses, icon: Building2, tone: 'orange' },
        { label: t('verifiedBusinessesStat'), value: statistics.verified_businesses, icon: BadgeCheck, tone: 'teal' },
    ];
    return <section className="public-stat-band" aria-label={t('platformStatistics')}><div>{cards.map(card => { const Icon = card.icon; return <article key={card.label}><span className={card.tone}><Icon size={21} /></span><div><strong>{card.value.toLocaleString()}</strong><small>{card.label}</small></div></article>; })}</div></section>;
}

function PublicSectionHeading({ eyebrow, title, description, id }: { eyebrow: string; title: string; description: string; id: string }) {
    return <div className="section-heading"><div><span>{eyebrow}</span><h2 id={id}>{title}</h2></div><p>{description}</p></div>;
}

function WhyPitchFlow() {
    const t = useTranslation();
    const features = [
        { icon: Clock3, title: t('realTimeAvailability'), text: t('realTimeAvailabilityText') },
        { icon: BadgeCheck, title: t('verifiedBusinessesFeature'), text: t('verifiedBusinessesFeatureText') },
        { icon: Zap, title: t('fastAndEasy'), text: t('fastAndEasyText') },
    ];
    return <section className="public-content-section why-pitchflow" id="why-pitchflow" aria-labelledby="why-pitchflow-heading">
        <PublicSectionHeading eyebrow="PitchFlow" title={t('whyPitchFlow')} description={t('whyPitchFlowIntro')} id="why-pitchflow-heading" />
        <div className="public-feature-grid">{features.map(feature => { const Icon = feature.icon; return <article key={feature.title}><span><Icon size={22} /></span><h3>{feature.title}</h3><p>{feature.text}</p></article>; })}</div>
    </section>;
}

function PartnerCallout() {
    const t = useTranslation();
    return <section className="partner-callout" id="partner"><div><span>{t('forFootballBusinesses')}</span><h2>{t('ownFootballField')}</h2><p>{t('partnerCalloutText')}</p></div><Link href="/register" className="btn partner-button">{t('registerBusiness')}<ChevronRight size={17} /></Link></section>;
}

function FrequentlyAskedQuestions() {
    const t = useTranslation();
    const questions = [
        [t('faqHow'), t('faqHowAnswer')],
        [t('faqFree'), t('faqFreeAnswer')],
        [t('faqReserve'), t('faqReserveAnswer')],
        [t('faqRegister'), t('faqRegisterAnswer')],
    ];
    return <section className="public-content-section public-faq" id="faq" aria-labelledby="faq-heading">
        <PublicSectionHeading eyebrow={t('helpCenter')} title={t('frequentlyAskedQuestions')} description={t('faqIntro')} id="faq-heading" />
        <div className="faq-list">{questions.map(([question, answer]) => <details key={question}><summary>{question}<ChevronRight size={18} /></summary><p>{answer}</p></details>)}</div>
    </section>;
}

function PublicFooter() {
    const t = useTranslation();
    return <footer className="public-footer"><div className="public-footer-main"><div className="public-footer-brand"><div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div><p>{t('footerDescription')}</p><span><CheckCircle2 size={15} />{t('availabilityOnly')}</span></div><nav aria-label={t('footerNavigation')}><div><strong>{t('platform')}</strong><a href="#why-pitchflow">{t('about')}</a><a href="mailto:hello@pitchflow.app">{t('contact')}</a><a href="#faq">FAQ</a></div><div><strong>{t('legal')}</strong><Link href="/privacy">{t('privacyPolicy')}</Link><Link href="/terms">{t('terms')}</Link></div><div><strong>{t('followUs')}</strong><a href="https://facebook.com" target="_blank" rel="noreferrer"><Facebook size={16} />Facebook</a><a href="https://instagram.com" target="_blank" rel="noreferrer"><Instagram size={16} />Instagram</a><a href="https://linkedin.com" target="_blank" rel="noreferrer"><Linkedin size={16} />LinkedIn</a></div></nav></div><div className="public-footer-bottom"><span>© {new Date().getFullYear()} PitchFlow. {t('allRightsReserved')}</span><span>{t('reservationsDirect')}</span></div></footer>;
}

export function PublicNav({ locale, dark, setLocale, setDark }: {
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

function SearchPanel({ cities, city, date, setCity, setDate, onSubmit }: {
    cities: Props['cities'];
    city: string;
    date: string;
    setCity: (value: string) => void;
    setDate: (value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    const t = useTranslation();
    return <form className="availability-search-card simple" onSubmit={onSubmit}>
        <span className="availability-search-icon" aria-hidden="true"><Search size={19} /></span>
        <label className="public-input">
            <span><MapPin size={18} /></span>
            <select aria-label={t('selectCity')} value={city} onChange={event => setCity(event.target.value)} required>
                <option value="">{t('selectCity')}</option>
                {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
            </select>
        </label>
        <CalendarPicker value={date} onChange={setDate} />
        <Button type="submit" className="availability-submit"><Search size={17} />{t('checkAvailabilityButton')}</Button>
    </form>;
}

function CalendarPicker({ value, onChange }: { value: string; onChange: (value: string) => void }) {
    const t = useTranslation();
    const selectedDate = parseDate(value);
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [popoverPosition, setPopoverPosition] = useState({ top: 0, left: 0 });
    const [visibleMonth, setVisibleMonth] = useState(() => startOfMonth(selectedDate));
    const calendarDays = useMemo(() => buildCalendarDays(visibleMonth), [visibleMonth]);
    const monthLabel = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(visibleMonth);
    const weekdays = useMemo(() => buildWeekdayLabels(), []);

    useEffect(() => {
        if (!open || !triggerRef.current) {
            return;
        }

        const updatePosition = () => {
            const rect = triggerRef.current?.getBoundingClientRect();

            if (!rect) {
                return;
            }

            const margin = 12;
            const popoverWidth = Math.min(330, window.innerWidth - margin * 2);
            const estimatedHeight = 360;
            const hasRoomBelow = window.innerHeight - rect.bottom >= estimatedHeight + margin;
            const top = hasRoomBelow
                ? rect.bottom + 8
                : Math.max(margin, rect.top - estimatedHeight - 8);
            const left = Math.min(
                Math.max(margin, rect.left),
                Math.max(margin, window.innerWidth - popoverWidth - margin),
            );

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

    return <div className="calendar-picker">
        <button
            type="button"
            ref={triggerRef}
            className="calendar-trigger"
            aria-label={t('chooseDate')}
            aria-expanded={open}
            onClick={() => setOpen(isOpen => !isOpen)}
        >
            <CalendarDays size={19} />
            <span>{formatDate(value)}</span>
        </button>
        {open && <div className="calendar-popover" style={{ top: popoverPosition.top, left: popoverPosition.left }} role="dialog" aria-label={t('chooseDate')}>
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
    const amenities = (business.amenities ?? []).filter(amenity => supportedAmenities.includes(amenity));
    const price = startingPrice(business.football_fields ?? [], business.currency ?? 'EUR');
    const pitchCount = business.football_fields?.length || business.number_of_fields || 1;
    return <article className="field-discovery-card">
        <div className="venue-cover light-cover" style={{ background: venueImages[index % venueImages.length] }}>
            <div className="venue-badges">
                {business.is_verified && <span className="verified"><BadgeCheck size={14} />{t('verified')}</span>}
                {business.is_new && <span className="new"><Sparkles size={13} />{t('newBusiness')}</span>}
            </div>
            <div className="venue-cover-lines" />
        </div>
        <div className="venue-card-body">
            <div className="venue-title-row">
                <div><h3>{business.name}</h3>
                <p><MapPin size={15} /> {business.city?.name}</p>
                </div>
                {business.operating_status && <span className={`operating-badge ${business.operating_status.is_open ? 'open' : 'closed'}`}><i />{business.operating_status.is_open ? t('openNow') : business.operating_status.opens_at ? `${t('closed')} · ${t('opensAt')} ${business.operating_status.opens_at}` : t('closed')}</span>}
            </div>
            {business.address && <p><MapPin size={15} /> {business.address}</p>}
            {business.phone && <a href={`tel:${business.phone.replaceAll(' ', '')}`}><Phone size={15} /> {business.phone}</a>}
            <p><Trophy size={15} /> {pitchLabel(t, pitchCount)}</p>
            {price && <p className="venue-price"><span>{t('startingAt')}</span><strong>{price}</strong></p>}
            {amenities.length > 0 && <AmenityList amenities={amenities} />}
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
    const icons = { parking: ParkingCircle, cafe: Coffee, showers: ShowerHead, indoor: Warehouse, outdoor: TreePine, lighting: Lightbulb };
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

function startingPrice(fields: PublicField[], currency: string) {
    const prices = fields
        .map(field => Number(field.price_per_hour))
        .filter(price => Number.isFinite(price));

    if (prices.length === 0) {
        return null;
    }

    return `${formatMoney(Math.min(...prices), currency)} / h`;
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
