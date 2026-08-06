import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ArrowUp, BadgeCheck, Building2, Check, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, Clock3, Coffee, Facebook, Lightbulb, MapPin, MapPinned, Moon, ParkingCircle, Phone, Search, ShieldCheck, ShowerHead, Sparkles, Sun, TreePine, Trophy, Warehouse, Zap } from 'lucide-react';
import { Button, Field, Input, Modal } from '../../Components/UI';
import { DatePicker } from '../../Components/DateControls';
import { slotStatusForAnalytics, trackPublicEvent } from '../../lib/analytics';
import { formatDateLabel, todayIso } from '../../lib/dateControls';
import { useTodayDate } from '../../hooks/useTodayDate';
import { setClientLocale, useLocale, useTranslation } from '../../lib/i18n';
import { getSlotStatus, zonedNowInput, type SlotStatus } from '../../lib/slotStatus';
import type { SharedProps } from '../../types';

export interface PublicField {
    id: number;
    name: string;
    status?: 'active' | 'closed' | 'maintenance';
    address?: string | null;
    price_per_hour?: string | number | null;
    opening_time?: string | null;
    closing_time?: string | null;
    city?: { id: number; name: string } | null;
}

export interface PublicBusiness {
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
        slots: Array<{ starts_at: string; ends_at: string; label: string; status: SlotStatus | 'occupied'; reservation_id?: number | null; timezone?: string }>;
    }>;
    filters: { city?: number | null; business?: number | null; date: string; client_now?: string | null };
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
    const { flash } = usePage<SharedProps>().props;
    const locale = useLocale();
    const today = useTodayDate();
    const selectedCity = cities.find(city => city.id === Number(filters.city));
    const dateWasExplicit = typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('date');
    const [draftCity, setDraftCity] = useState(filters.city ? String(filters.city) : '');
    const [draftDate, setDraftDate] = useState(filters.date);
    const [dateManuallySelected, setDateManuallySelected] = useState(dateWasExplicit);
    const [nowInput, setNowInput] = useState(() => zonedNowInput('Europe/Belgrade'));
    const initialHomeEvent = useRef({
        city_id: filters.city ?? null,
        organization_id: filters.business ?? null,
        metadata: { date: filters.date },
    });
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
        trackPublicEvent('public_home_view', initialHomeEvent.current);
    }, []);

    useEffect(() => {
        setDraftCity(filters.city ? String(filters.city) : '');
        setDraftDate(filters.date);
        setDateManuallySelected(typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('date'));
    }, [filters.city, filters.date]);
    useEffect(() => {
        if (!dateManuallySelected) {
            setDraftDate(today);
        }
    }, [dateManuallySelected, today]);
    useEffect(() => {
        const interval = window.setInterval(() => setNowInput(zonedNowInput('Europe/Belgrade')), 30000);
        return () => window.clearInterval(interval);
    }, []);

    const setLocale = (nextLocale: 'en' | 'sq') => {
        if (nextLocale === locale) return;
        setClientLocale(nextLocale);
        trackPublicEvent('language_switch', { metadata: { locale: nextLocale } });
        router.post('/locale', { locale: nextLocale }, { preserveScroll: true, preserveState: false, replace: true });
    };
    const navigate = (overrides: Partial<Props['filters']>) => router.get('/', {
        city: overrides.city ?? filters.city ?? undefined,
        date: overrides.date ?? filters.date,
        business: overrides.business ?? filters.business ?? undefined,
        client_now: zonedNowInput('Europe/Belgrade'),
    }, { preserveState: true, preserveScroll: true });
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!draftCity) {
            return;
        }

        trackPublicEvent('availability_search', {
            city_id: Number(draftCity),
            metadata: { date: draftDate },
        });
        router.get('/', { city: draftCity, date: draftDate, client_now: zonedNowInput('Europe/Belgrade') }, {
            preserveState: true,
            preserveScroll: false,
        });
    };
    const resetSearch = () => {
        trackPublicEvent('reset_search_click', {
            city_id: filters.city ?? null,
            organization_id: filters.business ?? null,
            metadata: { date: filters.date },
        });
        setDraftCity('');
        setDraftDate(todayIso());
        setDateManuallySelected(false);
        router.get('/', {}, { preserveState: false, preserveScroll: false });
    };
    const viewBusiness = (business: PublicBusiness) => {
        trackPublicEvent('business_view', { organization_id: business.id, city_id: business.city?.id ?? filters.city ?? null });
        navigate({ business: business.id });
    };
    const viewRecentBusiness = (business: PublicBusiness) => {
        trackPublicEvent('business_view', { organization_id: business.id, city_id: business.city?.id ?? null });
        router.get('/', { city: business.city?.id, business: business.id, date: draftDate, client_now: zonedNowInput('Europe/Belgrade') });
    };

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={t('checkAvailabilityTitle')} />
        <PublicNav dark={dark} setLocale={setLocale} setDark={setDark} />
        {flash.success && <div className="public-flash success">{flash.success}</div>}
        <main className="public-home">
            <section className="public-hero light">
                <div className="hero-inner">
                    <div className="hero-content">
                        <span className="hero-kicker"><Trophy size={16} /> {t('verifiedFields')}</span>
                        <h1>{t('checkAvailabilityTitle')}</h1>
                        <p>{t('availabilityHeroDescription')}</p>
                        <SearchPanel cities={cities} city={draftCity} date={draftDate} setCity={setDraftCity} setDate={value => { setDateManuallySelected(value !== today); setDraftDate(value); }} onSubmit={submitSearch} />
                        <HeroTrustBadges />
                        <div className="availability-only-note"><ShieldCheck size={17} /><span><strong>{t('availabilityOnly')}</strong>{t('reservationsDirect')}</span></div>
                    </div>
                </div>
            </section>

            {hasSearch && <div className="public-return-actions">
                <button type="button" onClick={resetSearch}><ChevronLeft size={16} />{t('backToHome')}</button>
                <button type="button" onClick={resetSearch}>{t('resetSearch')}</button>
            </div>}

            {!hasBusiness && !hasSearch && <>
                <PlatformStatistics statistics={statistics} />
                {recentBusinesses.length > 0 && <section className="public-content-section recent-fields" aria-labelledby="recently-added-heading">
                    <PublicSectionHeading eyebrow={t('latestOnPitchFlow')} title={t('recentlyAdded')} description={t('recentlyAddedIntro')} id="recently-added-heading" />
                    <div className="field-card-grid">
                        {recentBusinesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewRecentBusiness(business)} />)}
                    </div>
                    <div className="recent-fields-action">
                        <Link href="/football-fields" className="btn btn-secondary">{t('showMoreFields')}<ChevronRight size={16} /></Link>
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
                    {businesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewBusiness(business)} />)}
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

            {hasBusiness && selectedBusiness && <AvailabilitySection business={selectedBusiness} date={filters.date} pitchAvailability={pitchAvailability} now={nowInput} />}

            {!hasBusiness && !hasSearch && <>
                <WhyPitchFlow />
                <PartnerCallout />
                <FrequentlyAskedQuestions />
            </>}
        </main>
        {!hasBusiness && <PublicFooter />}
        <BackToTopButton />
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

function HeroTrustBadges() {
    const t = useTranslation();
    const badges = [
        { icon: BadgeCheck, label: t('verifiedFields') },
        { icon: Clock3, label: t('realtimeSchedule') },
        { icon: Phone, label: t('directContact') },
    ];

    return <div className="hero-trust-badges" aria-label={t('availabilityOnly')}>
        {badges.map(badge => {
            const Icon = badge.icon;
            return <span key={badge.label}><Icon size={15} />{badge.label}</span>;
        })}
    </div>;
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
    return <section className="partner-callout" id="partner"><div><span>{t('forFootballBusinesses')}</span><h2>{t('ownFootballField')}</h2><p>{t('partnerCalloutText')}</p></div><Link href="/register" className="btn partner-button" onClick={() => trackPublicEvent('register_business_click')}>{t('registerBusiness')}<ChevronRight size={17} /></Link></section>;
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

export function PublicFooter() {
    const t = useTranslation();
    return <footer className="public-footer"><div className="public-footer-main"><div className="public-footer-brand"><div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div><p>{t('footerDescription')}</p><span><CheckCircle2 size={15} />{t('availabilityOnly')}</span></div><nav aria-label={t('footerNavigation')}><div><strong>{t('platform')}</strong><a href="#why-pitchflow">{t('about')}</a><a href="mailto:pitchflowks@gmail.com">{t('contact')}</a><a href="#faq">FAQ</a></div><div><strong>{t('legal')}</strong><Link href="/privacy">{t('privacyPolicy')}</Link><Link href="/terms">{t('terms')}</Link></div><div><strong>{t('followUs')}</strong><a href="https://www.facebook.com/profile.php?id=61593008453044" target="_blank" rel="noreferrer"><Facebook size={16} />Facebook</a></div></nav></div><div className="public-footer-bottom"><span>© {new Date().getFullYear()} PitchFlow. {t('allRightsReserved')}</span><span>{t('reservationsDirect')}</span></div></footer>;
}

export function BackToTopButton() {
    const t = useTranslation();
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const updateVisibility = () => {
            const scrollable = document.documentElement.scrollHeight - window.innerHeight;
            const distanceFromBottom = scrollable - window.scrollY;

            setVisible(window.scrollY > 400 && distanceFromBottom < 700);
        };

        updateVisibility();
        window.addEventListener('scroll', updateVisibility, { passive: true });

        return () => window.removeEventListener('scroll', updateVisibility);
    }, []);

    return <button type="button" className={`back-to-top${visible ? ' visible' : ''}`} onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} aria-label={t('backToTop')} tabIndex={visible ? 0 : -1}>
        <ArrowUp size={16} />
        <span>{t('backToTop')}</span>
    </button>;
}

export function PublicNav({ dark, setLocale, setDark }: {
    dark: boolean;
    setLocale: (locale: 'en' | 'sq') => void;
    setDark: (dark: boolean) => void;
}) {
    const t = useTranslation();
    const activeLocale = useLocale();
    const [languageOpen, setLanguageOpen] = useState(false);
    const languageOptions = [
        { code: 'en' as const, short: 'EN', label: 'English', flag: '🇬🇧' },
        { code: 'sq' as const, short: 'SQ', label: 'Shqip', flag: '🇦🇱' },
    ];
    const activeLanguage = languageOptions.find(option => option.code === activeLocale) ?? languageOptions[1];
    const chooseLanguage = (nextLocale: 'en' | 'sq') => {
        setLanguageOpen(false);
        setLocale(nextLocale);
    };

    return <nav className="public-nav">
        <div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div>
        <div className="public-nav-actions">
            <div className="language-selector public-language-selector">
                <button type="button" className="language-selector-trigger" onClick={() => setLanguageOpen(isOpen => !isOpen)} aria-expanded={languageOpen} aria-label={t('language')}>
                    <span aria-hidden="true">{activeLanguage.flag}</span><strong>{activeLanguage.short}</strong><ChevronDown size={14} />
                </button>
                {languageOpen && <div className="language-selector-menu">
                    {languageOptions.map(option => <button key={option.code} type="button" className={activeLocale === option.code ? 'active' : ''} onClick={() => chooseLanguage(option.code)}>
                        <span aria-hidden="true">{option.flag}</span><strong>{option.label}</strong>{activeLocale === option.code && <Check size={15} />}
                    </button>)}
                </div>}
            </div>
            <div className="public-mobile-language-buttons" aria-label={t('language')}>
                {languageOptions.map(option => <button key={option.code} type="button" className={activeLocale === option.code ? 'active' : ''} onClick={() => chooseLanguage(option.code)} aria-pressed={activeLocale === option.code}>
                    <span aria-hidden="true">{option.flag}</span><strong>{option.short}</strong>
                </button>)}
            </div>
            <button className="public-theme-toggle" onClick={() => setDark(!dark)} title={dark ? 'Light mode' : 'Dark mode'} aria-label={dark ? 'Light mode' : 'Dark mode'}>
                {dark ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <Link href="/login" className="btn btn-secondary" onClick={() => trackPublicEvent('login_click')}>{t('login')}</Link>
            <Link href="/register" className="btn btn-primary" onClick={() => trackPublicEvent('register_business_click')}><span className="desktop-label">{t('register')}</span><span className="mobile-label">{t('registerShort')}</span></Link>
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
        <label className="public-input">
            <span><MapPin size={18} /></span>
            <select aria-label={t('selectCity')} value={city} onChange={event => {
                setCity(event.target.value);
                if (event.target.value) {
                    trackPublicEvent('city_selected', { city_id: Number(event.target.value) });
                }
            }} required>
                <option value="">{t('selectCity')}</option>
                {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
            </select>
        </label>
        <DatePicker value={date} onChange={setDate} />
        <Button type="submit" className="availability-submit"><Search size={17} />{t('checkAvailabilityButton')}</Button>
    </form>;
}

export function BusinessCard({ business, index, onView }: { business: PublicBusiness; index: number; onView: () => void }) {
    const t = useTranslation();
    const amenities = (business.amenities ?? []).filter(amenity => supportedAmenities.includes(amenity));
    const price = startingPrice(business.football_fields ?? [], business.currency ?? 'EUR');
    const pitchCount = business.football_fields?.length || business.number_of_fields || 1;
    const hours = openingHours(business.football_fields ?? []);
    const fieldStatuses = visibleFieldStatuses(business.football_fields ?? []);
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
            <div className="venue-meta-grid">
                {hours && <p><Clock3 size={15} /><span>{t('workingHours')}</span><strong>{hours}</strong></p>}
                <p><Trophy size={15} /><span>{t('fields')}</span><strong>{pitchLabel(t, pitchCount)}</strong></p>
            </div>
            {price && <p className="venue-price"><span>{t('startingAt')}</span><strong>{price}</strong></p>}
            {fieldStatuses.length > 0 && <div className="venue-status-list">{fieldStatuses.map(status => <span key={status} className={`venue-status-badge ${status}`}>{t(status === 'maintenance' ? 'underMaintenance' : 'closed')}</span>)}</div>}
            {amenities.length > 0 && <AmenityList amenities={amenities} />}
            <div className="venue-card-actions">
                <Button className="card-cta" onClick={onView}>{t('viewAvailability')} <ChevronRight size={15} /></Button>
                {business.phone && <a className="card-call-cta" href={phoneHref(business.phone)} onClick={() => trackPublicEvent('call_click', { organization_id: business.id, city_id: business.city?.id ?? null })}><Phone size={15} />{t('callToReserve')}</a>}
            </div>
        </div>
    </article>;
}

function AvailabilitySection({ business, date, pitchAvailability, now }: {
    business: PublicBusiness;
    date: string;
    pitchAvailability: Props['pitchAvailability'];
    now: string;
}) {
    const t = useTranslation();
    const locale = useLocale();
    const trackedViews = useRef(new Set<string>());
    const [waitingSlot, setWaitingSlot] = useState<{
        field: PublicField;
        slot: Props['pitchAvailability'][number]['slots'][number];
    } | null>(null);
    const [joinedWaitingList, setJoinedWaitingList] = useState(false);
    const form = useForm({
        football_field_id: 0,
        reservation_id: null as number | null,
        starts_at: '',
        ends_at: '',
        customer_name: '',
        phone: '',
        note: '',
    });
    const openWaitingList = (field: PublicField, slot: Props['pitchAvailability'][number]['slots'][number]) => {
        setWaitingSlot({ field, slot });
        form.clearErrors();
        form.setData({
            football_field_id: field.id,
            reservation_id: slot.reservation_id ?? null,
            starts_at: slot.starts_at,
            ends_at: slot.ends_at,
            customer_name: '',
            phone: '',
            note: '',
        });
    };
    useEffect(() => {
        pitchAvailability.forEach(pitch => {
            const fieldKey = `field:${pitch.field.id}:${date}`;
            if (!trackedViews.current.has(fieldKey)) {
                trackedViews.current.add(fieldKey);
                trackPublicEvent('field_view', {
                    organization_id: business.id,
                    football_field_id: pitch.field.id,
                    city_id: business.city?.id ?? pitch.field.city?.id ?? null,
                    metadata: { date },
                });
            }

            pitch.slots.forEach(slot => {
                const slotKey = `slot:${pitch.field.id}:${slot.starts_at}:${slot.ends_at}`;
                if (trackedViews.current.has(slotKey)) return;
                trackedViews.current.add(slotKey);
                trackPublicEvent('availability_slot_view', {
                    organization_id: business.id,
                    football_field_id: pitch.field.id,
                    city_id: business.city?.id ?? pitch.field.city?.id ?? null,
                    metadata: {
                        date,
                        start: slot.starts_at,
                        end: slot.ends_at,
                        status: slotStatusForAnalytics(slot.status),
                    },
                });
            });
        });
    }, [business.city?.id, business.id, date, pitchAvailability]);
    const submitWaitingList = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/waiting-list', {
            preserveScroll: true,
            onSuccess: () => {
                setWaitingSlot(null);
                setJoinedWaitingList(true);
            },
        });
    };

    return <section className="availability-results focused" aria-labelledby="availability-results-heading">
        <div className="availability-header">
            <div>
                <h2 id="availability-results-heading">{business.name} — {business.city?.name}</h2>
                <p>{formatDate(date, locale)}</p>
            </div>
            <div className="availability-contact-actions">
                <span>{t('callAfterCheckingAvailability')}</span>
                {business.phone && <a href={phoneHref(business.phone)} onClick={() => trackPublicEvent('call_click', { organization_id: business.id, city_id: business.city?.id ?? null })}><Phone size={16} /> {business.phone}</a>}
            </div>
        </div>
        <div className="pitch-availability-list">
            {joinedWaitingList && <div className="waiting-list-success"><CheckCircle2 size={17} />{t('waitingListJoined')}</div>}
            {pitchAvailability.map((pitch, index) => <section className="pitch-slots" key={pitch.field.id}>
                <div className="pitch-slots-header">
                    <div>
                        <h3>{pitch.field.name || `${t('footballPitch')} ${index + 1}`}</h3>
                        {pitch.field.status && pitch.field.status !== 'active' && <span className={`venue-status-badge ${pitch.field.status}`}>{t(pitch.field.status === 'maintenance' ? 'underMaintenance' : 'closed')}</span>}
                    </div>
                    {pitch.field.price_per_hour !== undefined && pitch.field.price_per_hour !== null && <span>{formatMoney(pitch.field.price_per_hour, business.currency ?? 'EUR')} / h</span>}
                </div>
                {pitch.slots.length === 0
                    ? <ClosedAvailabilityNotice field={pitch.field} date={date} />
                    : <div className="slot-card-grid compact">
                        {pitch.slots.map(slot => <TimeSlotCard key={slot.starts_at} slot={slot} now={now} onJoinWaitlist={() => openWaitingList(pitch.field, slot)} />)}
                    </div>}
            </section>)}
        </div>
        <Modal open={Boolean(waitingSlot)} title={t('joinWaitingList')} onClose={() => setWaitingSlot(null)} className="waiting-list-modal">
            <form className="waiting-list-form" onSubmit={submitWaitingList}>
                <p>{t('waitingListIntro')}</p>
                {waitingSlot && <div className="waiting-slot-summary"><strong>{waitingSlot.field.name}</strong><span>{waitingSlot.slot.label}</span></div>}
                <div className="form-grid">
                    <Field label={t('customerName')} error={form.errors.customer_name} required><Input autoFocus value={form.data.customer_name} onChange={event => form.setData('customer_name', event.target.value)} /></Field>
                    <Field label={t('phone')} error={form.errors.phone} required><Input inputMode="tel" autoComplete="tel" value={form.data.phone} onChange={event => form.setData('phone', sanitizePhoneInput(event.target.value))} /></Field>
                    <Field label={t('optionalNote')} error={form.errors.note}><textarea className="input" value={form.data.note} onChange={event => form.setData('note', event.target.value)} /></Field>
                </div>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setWaitingSlot(null)}>{t('close')}</Button><Button disabled={form.processing}>{t('joinList')}</Button></div>
            </form>
        </Modal>
    </section>;
}

function ClosedAvailabilityNotice({ field, date }: { field: PublicField; date: string }) {
    const t = useTranslation();
    const locale = useLocale();

    if (field.status === 'closed') {
        return <div className="availability-closed-notice"><strong>{t('closedToday')}</strong><p>{t('fieldCurrentlyClosedMessage')}</p></div>;
    }

    if (field.status === 'maintenance') {
        return <div className="availability-closed-notice maintenance"><strong>{t('underMaintenance')}</strong><p>{t('fieldUnderMaintenanceMessage')}</p></div>;
    }

    return <div className="availability-closed-notice"><strong>{t('closedToday')}</strong><p>{weekdayClosedMessage(date, locale)}</p></div>;
}

function TimeSlotCard({ slot, now, onJoinWaitlist }: { slot: Props['pitchAvailability'][number]['slots'][number]; now: string; onJoinWaitlist: () => void }) {
    const t = useTranslation();
    const calculatedStatus = getSlotStatus({
        selectedDate: slot.starts_at.slice(0, 10),
        startTime: slot.starts_at.slice(11, 16),
        endTime: slot.ends_at.slice(11, 16),
        reservationStatus: slot.reservation_id ? 'reserved' : slot.status === 'closed' ? 'closed' : null,
        timezone: slot.timezone ?? 'Europe/Belgrade',
        now,
    });
    const isReserved = calculatedStatus === 'reserved';
    const label = calculatedStatus === 'available'
        ? t('available')
        : isReserved
            ? t('reserved')
            : calculatedStatus === 'current'
                ? t('currentSlot')
                : calculatedStatus === 'closed'
                    ? t('closed')
                    : t('past');

    return <div className={`time-slot-card compact ${calculatedStatus}`}>
        <strong>{slot.label}</strong>
        <span>{label}</span>
        {isReserved && <button type="button" className="waitlist-slot-button" onClick={onJoinWaitlist}>{t('notifyMeIfFree')}</button>}
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

function openingHours(fields: PublicField[]) {
    const field = fields.find(item => item.opening_time && item.closing_time);

    if (!field?.opening_time || !field?.closing_time) {
        return null;
    }

    return `${field.opening_time.slice(0, 5)} - ${field.closing_time.slice(0, 5)}`;
}

function visibleFieldStatuses(fields: PublicField[]) {
    return Array.from(new Set(fields.map(field => field.status).filter((status): status is 'closed' | 'maintenance' => status === 'closed' || status === 'maintenance')));
}

function weekdayClosedMessage(date: string, locale: string) {
    const weekday = new Date(`${date}T12:00:00`).getDay();
    const enWeekdays = ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'];
    const sqWeekdays = ['dielave', 'hënave', 'martave', 'mërkurave', 'enjteve', 'premteve', 'shtunave'];

    return locale === 'sq'
        ? `Kjo fushë nuk punon të ${sqWeekdays[weekday]}. Ju lutemi zgjidhni një datë tjetër.`
        : `This field is closed on ${enWeekdays[weekday]}. Please choose another date.`;
}

function formatMoney(value: string | number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: Number(value) % 1 === 0 ? 0 : 2,
    }).format(Number(value));
}

function phoneHref(phone: string) {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

function sanitizePhoneInput(value: string) {
    const hasLeadingPlus = value.trimStart().startsWith('+');
    const withoutPluses = value.replace(/\+/g, '');
    const readablePhone = withoutPluses.replace(/[^\d ]/g, '').replace(/\s{2,}/g, ' ');

    return hasLeadingPlus ? `+${readablePhone.trimStart()}` : readablePhone;
}

function formatDate(date: string, locale: string) {
    return formatDateLabel(date, locale);
}
