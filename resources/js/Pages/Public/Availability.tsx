import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Coffee, Languages, MapPin, ParkingCircle, Phone, Search, ShowerHead, Trophy } from 'lucide-react';
import { Button } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

interface PublicField {
    id: number;
    name: string;
    address?: string | null;
    city?: { id: number; name: string } | null;
}

interface PublicBusiness {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    number_of_fields?: number | null;
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

    const setLocale = (nextLocale: 'en' | 'sq') => router.post('/locale', { locale: nextLocale }, { preserveScroll: true });
    const navigate = (overrides: Partial<Props['filters']>) => router.get('/', {
        city: overrides.city ?? filters.city ?? undefined,
        date: overrides.date ?? filters.date,
        business: overrides.business ?? filters.business ?? undefined,
    }, { preserveState: true, preserveScroll: true });
    const setCity = (value: string) => router.get('/', { city: value || undefined, date: filters.date }, { preserveState: true, preserveScroll: true, replace: true });
    const setDate = (date: string) => navigate({ date });
    const shiftDate = (days: number) => setDate(dateOffset(filters.date, days));
    const viewBusiness = (businessId: number) => navigate({ business: businessId });

    return <div className="public-page">
        <Head title={t('checkAvailabilityTitle')} />
        <PublicNav locale={locale} setLocale={setLocale} />
        <main className="public-home">
            <section className="public-hero light">
                <div className="hero-content">
                    <span className="hero-kicker"><Trophy size={16} /> {t('verifiedFields')}</span>
                    <h1>{t('checkAvailabilityTitle')}</h1>
                    <p>{t('availabilityHeroDescription')}</p>
                    <SearchPanel cities={cities} filters={filters} setCity={setCity} setDate={setDate} shiftDate={shiftDate} />
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

function PublicNav({ locale, setLocale }: { locale: 'en' | 'sq'; setLocale: (locale: 'en' | 'sq') => void }) {
    const t = useTranslation();
    return <nav className="public-nav">
        <div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div>
        <div className="public-nav-actions">
            <div className="language-switcher" aria-label={t('language')}>
                <Languages size={16} />
                <button className={locale === 'en' ? 'active' : ''} onClick={() => setLocale('en')}>EN</button>
                <button className={locale === 'sq' ? 'active' : ''} onClick={() => setLocale('sq')}>SQ</button>
            </div>
            <Link href="/login" className="btn btn-secondary">{t('login')}</Link>
            <Link href="/register" className="btn btn-primary"><span className="desktop-label">{t('register')}</span><span className="mobile-label">{t('registerShort')}</span></Link>
        </div>
    </nav>;
}

function SearchPanel({ cities, filters, setCity, setDate, shiftDate }: {
    cities: Props['cities'];
    filters: Props['filters'];
    setCity: (value: string) => void;
    setDate: (value: string) => void;
    shiftDate: (days: number) => void;
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
        <div className="date-navigator">
            <Button variant="secondary" onClick={() => shiftDate(-1)}><ChevronLeft size={17} />{t('previousDay')}</Button>
            <div className="date-display">
                <CalendarDays size={18} />
                <strong>{formatDate(filters.date)}</strong>
                <label className="date-picker">
                    <input aria-label={t('chooseDate')} type="date" value={filters.date} onChange={event => setDate(event.target.value)} required />
                </label>
            </div>
            <Button variant="secondary" onClick={() => setDate(today())}>{t('today')}</Button>
            <Button variant="secondary" onClick={() => shiftDate(1)}>{t('nextDay')}<ChevronRight size={17} /></Button>
        </div>
    </div>;
}

function BusinessCard({ business, index, onView }: { business: PublicBusiness; index: number; onView: () => void }) {
    const t = useTranslation();
    const amenities = demoAmenities[business.name] ?? ['parking'];
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
                <h3>{pitch.field.name || `${t('footballPitch')} ${index + 1}`}</h3>
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

function dateOffset(date: string, days: number) {
    const next = new Date(`${date}T12:00:00`);
    next.setDate(next.getDate() + days);
    return next.toISOString().slice(0, 10);
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function formatDate(date: string) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${date}T12:00:00`));
}
