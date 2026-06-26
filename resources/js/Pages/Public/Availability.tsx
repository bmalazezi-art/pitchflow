import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, CalendarDays, CheckCircle2, ChevronRight, Coffee, Languages, MapPin, ParkingCircle, Phone, Search, ShowerHead, Trophy } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

interface PublicField {
    id: number;
    name: string;
    address?: string | null;
    city?: { id: number; name: string } | null;
    organization: {
        name: string;
        phone?: string | null;
        number_of_fields?: number | null;
        city?: { id: number; name: string } | null;
    };
}

interface Props {
    cities: Array<{ id: number; name: string }>;
    fields: PublicField[];
    selectedField?: PublicField | null;
    slots: Array<{ starts_at: string; ends_at: string; label: string; status: string }>;
    filters: { city?: number | null; field?: number | null; date: string };
}

type Amenity = 'parking' | 'cafe' | 'showers';

const venueImages = [
    'linear-gradient(135deg, #0f766e 0%, #16a34a 48%, #bef264 100%)',
    'linear-gradient(135deg, #1d4ed8 0%, #0284c7 48%, #67e8f9 100%)',
    'linear-gradient(135deg, #14532d 0%, #22c55e 50%, #facc15 100%)',
    'linear-gradient(135deg, #0f172a 0%, #334155 52%, #38bdf8 100%)',
];

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

export default function Availability({ cities, fields, selectedField, slots, filters }: Props) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const [draftField, setDraftField] = useState<number | null>(filters.field ?? null);
    const [draftDate, setDraftDate] = useState(filters.date);
    const selectedCity = cities.find(city => city.id === Number(filters.city));
    const hasResults = Boolean(selectedField);
    const searchFilters = { ...filters, field: draftField, date: draftDate };

    const setLocale = (nextLocale: 'en' | 'sq') => router.post('/locale', { locale: nextLocale }, { preserveScroll: true });
    const updateFilter = (key: 'city' | 'field' | 'date', value: string | number | null) => {
        if (key === 'city') {
            setDraftField(null);
            router.get('/', { city: value || undefined, date: draftDate }, { preserveState: true, preserveScroll: true, replace: true });
            return;
        }
        if (key === 'field') {
            setDraftField(value ? Number(value) : null);
            return;
        }
        setDraftDate(String(value || filters.date));
    };
    const checkAvailability = () => {
        if (!draftField && fields.length === 1) {
            router.get('/', { ...filters, date: draftDate, field: fields[0].id }, { preserveState: true });
            return;
        }
        router.get('/', { ...filters, date: draftDate, field: draftField || undefined }, { preserveState: true });
    };

    return <div className="public-page">
        <Head title={t('checkAvailabilityTitle')} />
        <PublicNav locale={locale} setLocale={setLocale} />
        {hasResults && selectedField
            ? <ResultsView selectedField={selectedField} slots={slots} filters={searchFilters} cities={cities} fields={fields} updateFilter={updateFilter} />
            : <main className="public-home">
                <section className="public-hero">
                    <div className="hero-visual" aria-hidden="true"><div className="field-lines" /></div>
                    <div className="hero-content">
                        <span className="hero-kicker"><Trophy size={16} /> {t('verifiedFields')}</span>
                        <h1>{t('checkAvailabilityTitle')}</h1>
                        <p>{t('availabilityHeroDescription')}</p>
                        <SearchCard cities={cities} fields={fields} filters={searchFilters} updateFilter={updateFilter} onSubmit={checkAvailability} />
                    </div>
                </section>
                <section className="field-discovery" aria-labelledby="discover-fields">
                    <div className="section-heading">
                        <div>
                            <span>{selectedCity ? selectedCity.name : t('chooseCity')}</span>
                            <h2 id="discover-fields">{t('discoverFootballFields')}</h2>
                        </div>
                        <p>{t('discoverFootballFieldsIntro')}</p>
                    </div>
                    <div className="field-card-grid">
                        {fields.map((field, index) => <FieldCard key={field.id} field={field} index={index} selected={draftField === field.id} onSelect={() => updateFilter('field', field.id)} />)}
                    </div>
                    {fields.length === 0 && <div className="public-empty"><Search size={24} /><p>{t('selectCityToDiscover')}</p></div>}
                </section>
            </main>}
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

function SearchCard({ cities, fields, filters, updateFilter, onSubmit }: {
    cities: Props['cities'];
    fields: PublicField[];
    filters: Props['filters'];
    updateFilter: (key: 'city' | 'field' | 'date', value: string | number | null) => void;
    onSubmit: () => void;
}) {
    const t = useTranslation();
    return <div className="availability-search-card">
        <IconSelect icon={<MapPin size={18} />} label={t('selectCity')} value={filters.city ?? ''} onChange={value => updateFilter('city', value)}>
            <option value="">{t('selectCity')}</option>
            {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
        </IconSelect>
        <IconSelect icon={<Building2 size={18} />} label={t('selectField')} value={filters.field ?? ''} onChange={value => updateFilter('field', value)}>
            <option value="">{t('selectField')}</option>
            {fields.map(field => <option key={field.id} value={field.id}>{field.organization.name}</option>)}
        </IconSelect>
        <IconInput icon={<CalendarDays size={18} />} label={t('chooseDate')} type="date" value={filters.date} onChange={value => updateFilter('date', value)} />
        <Button className="availability-submit" onClick={onSubmit}><Search size={18} />{t('checkAvailability')}</Button>
    </div>;
}

function IconSelect({ icon, label, value, onChange, children }: {
    icon: ReactNode;
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    children: ReactNode;
}) {
    return <label className="public-input">
        <span>{icon}</span>
        <select aria-label={label} value={value} onChange={event => onChange(event.target.value)}>{children}</select>
    </label>;
}

function IconInput({ icon, label, type, value, onChange }: {
    icon: ReactNode;
    label: string;
    type: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return <label className="public-input">
        <span>{icon}</span>
        <input aria-label={label} type={type} value={value} onChange={event => onChange(event.target.value)} />
    </label>;
}

function FieldCard({ field, index, selected, onSelect }: { field: PublicField; index: number; selected: boolean; onSelect: () => void }) {
    const t = useTranslation();
    const amenities = demoAmenities[field.organization.name] ?? ['parking'];
    return <article
        className={`field-discovery-card ${selected ? 'selected' : ''}`}
        role="button"
        tabIndex={0}
        onClick={onSelect}
        onKeyDown={event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onSelect();
            }
        }}
    >
        <div className="venue-cover" style={{ background: venueImages[index % venueImages.length] }}>
            <div className="venue-cover-lines" />
        </div>
        <div className="venue-card-body">
            <div>
                <h3>{field.organization.name}</h3>
                <p><MapPin size={15} /> {cityName(field)}</p>
            </div>
            {field.address && <p><MapPin size={15} /> {field.address}</p>}
            {field.organization.phone && <a href={`tel:${field.organization.phone.replaceAll(' ', '')}`} onClick={event => event.stopPropagation()}><Phone size={15} /> {field.organization.phone}</a>}
            <p><Trophy size={15} /> {pitchLabel(t, field.organization.number_of_fields ?? 1)}</p>
            <AmenityList amenities={amenities} />
            <span className="card-cta">{t('selectField')} <ChevronRight size={15} /></span>
        </div>
    </article>;
}

function ResultsView({ selectedField, slots, filters, cities, fields, updateFilter }: {
    selectedField: PublicField;
    slots: Props['slots'];
    filters: Props['filters'];
    cities: Props['cities'];
    fields: PublicField[];
    updateFilter: (key: 'city' | 'field' | 'date', value: string | number | null) => void;
}) {
    const t = useTranslation();
    const amenities = demoAmenities[selectedField.organization.name] ?? ['parking'];
    return <main className="availability-results">
        <section className="results-top">
            <button className="back-link" onClick={() => router.get('/', { city: filters.city, date: filters.date }, { preserveState: true })}>{t('backToSearch')}</button>
            <h1>{selectedField.organization.name}</h1>
            <p>{t('resultsIntro')}</p>
            <SearchCard cities={cities} fields={fields} filters={filters} updateFilter={updateFilter} onSubmit={() => router.get('/', filters, { preserveState: true })} />
        </section>
        <section className="selected-venue-card">
            <div className="venue-cover results-cover"><div className="venue-cover-lines" /></div>
            <div className="selected-venue-content">
                <span>{t('selectedFootballField')}</span>
                <h2>{selectedField.organization.name}</h2>
                <div className="venue-details">
                    <p><MapPin size={16} /> {cityName(selectedField)}</p>
                    {selectedField.organization.phone && <a href={`tel:${selectedField.organization.phone.replaceAll(' ', '')}`}><Phone size={16} /> {selectedField.organization.phone}</a>}
                    <p><Trophy size={16} /> {pitchLabel(t, selectedField.organization.number_of_fields ?? 1)}</p>
                </div>
                <AmenityList amenities={amenities} />
            </div>
        </section>
        <section className="slot-section" aria-labelledby="slot-heading">
            <div className="section-heading">
                <div>
                    <span>{filters.date}</span>
                    <h2 id="slot-heading">{t('availableTimeSlots')}</h2>
                </div>
                <p>{t('privacyNotice')}</p>
            </div>
            <div className="slot-card-grid">
                {slots.map(slot => <TimeSlotCard key={slot.starts_at} slot={slot} />)}
            </div>
        </section>
    </main>;
}

function TimeSlotCard({ slot }: { slot: Props['slots'][number] }) {
    const t = useTranslation();
    const Icon = slot.status === 'available' ? CheckCircle2 : slot.status === 'occupied' ? Phone : CalendarDays;
    return <div className={`time-slot-card ${slot.status}`}>
        <div className="slot-status-icon"><Icon size={22} /></div>
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

function cityName(field: PublicField) {
    return field.city?.name ?? field.organization.city?.name ?? '';
}

function pitchLabel(t: ReturnType<typeof useTranslation>, count: number) {
    return `${count} ${count === 1 ? t('footballPitch') : t('footballPitches')}`;
}
