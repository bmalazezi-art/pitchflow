import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { ChevronLeft, MapPin, Search, ShieldCheck } from 'lucide-react';
import { Button } from '../../Components/UI';
import { DatePicker } from '../../Components/DateControls';
import { trackPublicEvent } from '../../lib/analytics';
import { useTranslation } from '../../lib/i18n';
import { zonedNowInput } from '../../lib/slotStatus';
import type { SharedProps } from '../../types';
import { BusinessCard, PublicFooter, PublicNav, type PublicBusiness } from './Availability';

interface Props {
    cities: Array<{ id: number; name: string }>;
    businesses: PublicBusiness[];
    filters: { city?: number | null; search?: string | null; date: string };
}

export default function Fields({ cities, businesses, filters }: Props) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const [dark, setDark] = useState(() => localStorage.getItem('public-theme') === 'dark');
    const [draftCity, setDraftCity] = useState(filters.city ? String(filters.city) : '');
    const [draftSearch, setDraftSearch] = useState(filters.search ?? '');
    const [draftDate, setDraftDate] = useState(filters.date);

    useEffect(() => {
        localStorage.setItem('public-theme', dark ? 'dark' : 'light');
    }, [dark]);

    useEffect(() => {
        setDraftCity(filters.city ? String(filters.city) : '');
        setDraftSearch(filters.search ?? '');
        setDraftDate(filters.date);
    }, [filters.city, filters.date, filters.search]);

    const setLocale = (nextLocale: 'en' | 'sq') => {
        if (nextLocale === locale) return;
        localStorage.setItem('locale', nextLocale);
        trackPublicEvent('language_switch', { metadata: { locale: nextLocale, source: 'public_fields' } });
        router.post('/locale', { locale: nextLocale }, { preserveScroll: true, preserveState: false });
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/football-fields', {
            city: draftCity || undefined,
            search: draftSearch || undefined,
            date: draftDate,
        }, { preserveState: true, preserveScroll: false });
    };
    const clear = () => router.get('/football-fields', {}, { preserveState: false, preserveScroll: false });
    const viewBusiness = (business: PublicBusiness) => {
        trackPublicEvent('business_view', { organization_id: business.id, city_id: business.city?.id ?? null, metadata: { source: 'public_fields' } });
        router.get('/', {
            city: business.city?.id,
            business: business.id,
            date: draftDate,
            client_now: zonedNowInput('Europe/Belgrade'),
        });
    };

    return <div className={`public-page ${dark ? 'public-dark' : ''}`}>
        <Head title={t('allFootballFields')} />
        <PublicNav locale={locale} dark={dark} setLocale={setLocale} setDark={setDark} />
        <main className="public-fields-page">
            <section className="public-directory-hero">
                <button type="button" className="legal-back" onClick={() => router.get('/')}><ChevronLeft size={16} />{t('backToHome')}</button>
                <span><ShieldCheck size={16} />{t('verifiedFields')}</span>
                <h1>{t('allFootballFields')}</h1>
                <p>{t('allFootballFieldsIntro')}</p>
                <form className="public-directory-filters" onSubmit={submit}>
                    <label className="public-input">
                        <MapPin size={18} />
                        <select aria-label={t('selectCity')} value={draftCity} onChange={event => {
                            setDraftCity(event.target.value);
                            if (event.target.value) {
                                trackPublicEvent('city_selected', { city_id: Number(event.target.value), metadata: { source: 'public_fields' } });
                            }
                        }}>
                            <option value="">{t('allCities')}</option>
                            {cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}
                        </select>
                    </label>
                    <label className="public-input">
                        <Search size={18} />
                        <input value={draftSearch} onChange={event => setDraftSearch(event.target.value)} placeholder={t('searchFieldsPlaceholder')} />
                    </label>
                    <DatePicker value={draftDate} onChange={setDraftDate} />
                    <Button type="submit" className="availability-submit"><Search size={17} />{t('apply')}</Button>
                </form>
                {(filters.city || filters.search) && <button type="button" className="public-clear-link" onClick={clear}>{t('clearFilters')}</button>}
            </section>

            <section className="field-discovery public-directory-list" aria-labelledby="public-fields-heading">
                <div className="section-heading">
                    <div>
                        <span>{t('platform')}</span>
                        <h2 id="public-fields-heading">{t('footballFieldsFoundCount').replace(':count', String(businesses.length))}</h2>
                    </div>
                    <p>{t('publicFieldsSafetyNote')}</p>
                </div>
                {businesses.length > 0
                    ? <div className="field-card-grid">{businesses.map((business, index) => <BusinessCard key={business.id} business={business} index={index} onView={() => viewBusiness(business)} />)}</div>
                    : <div className="public-empty"><Search size={24} /><h2>{t('noFootballFieldsInCity')}</h2><p>{t('selectCityToDiscover')}</p></div>}
            </section>
        </main>
        <PublicFooter />
    </div>;
}
