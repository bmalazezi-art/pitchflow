import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, Check, ChevronLeft, ChevronRight, Clock3, MapPin, ShieldCheck, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

const amenityKeys = ['parking', 'cafe', 'showers', 'indoor', 'outdoor', 'lighting'] as const;
const registrationStepForErrors = (errors: Record<string, string>) => {
    const errorKeys = Object.keys(errors);
    if (errorKeys.some(key => ['name', 'email', 'owner_phone', 'password', 'password_confirmation'].includes(key))) return 1;
    if (errorKeys.some(key => ['business_name', 'business_phone', 'city_id', 'business_address', 'preferred_language'].includes(key))) return 2;
    return 3;
};

export default function Register({ cities }: { cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [step, setStep] = useState(1);
    const form = useForm({ name: '', email: '', owner_phone: '', password: '', password_confirmation: '', business_name: '', business_phone: '', city_id: '', business_address: '', preferred_language: 'en', number_of_fields: 1, starting_price_per_hour: 0, opening_time: '12:00', closing_time: '01:00', amenities: [] as string[] });
    const stepFields = step === 1 ? ['name', 'email', 'owner_phone', 'password', 'password_confirmation'] : step === 2 ? ['business_name', 'business_phone', 'city_id', 'business_address'] : ['number_of_fields', 'starting_price_per_hour', 'opening_time', 'closing_time'];
    const canContinue = stepFields.every(key => String(form.data[key as keyof typeof form.data] ?? '').trim() !== '');
    const toggleAmenity = (key: string) => form.setData('amenities', form.data.amenities.includes(key) ? form.data.amenities.filter(item => item !== key) : [...form.data.amenities, key]);
    useEffect(() => {
        if (Object.keys(form.errors).length > 0) setStep(registrationStepForErrors(form.errors));
    }, [form.errors]);

    const submit = () => form.post('/register', {
        preserveScroll: false,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });

    return <AuthLayout wide><Head title={t('register')} /><div className="registration-shell">
        <header className="registration-heading"><span className="registration-kicker"><ShieldCheck size={16} />{t('businessApplication')}</span><h1>{t('createBusinessWorkspace')}</h1><p>{t('applicationsReviewed')}</p></header>
        <nav className="registration-progress" aria-label={t('registrationProgress')}>{[
            [1, t('ownerInformation'), UserRound], [2, t('businessInformation'), Building2], [3, t('fieldSetup'), MapPin],
        ].map(([number, label, Icon]: any) => <div key={number} className={step >= number ? 'active' : ''}><span>{step > number ? <Check size={17} /> : <Icon size={17} />}</span><strong>{label}</strong></div>)}</nav>

        <form className="registration-card" onSubmit={event => { event.preventDefault(); submit(); }}>
            {Object.keys(form.errors).length > 0 && <div className="registration-error" role="alert">{t('registrationError')}</div>}
            {step === 1 && <section><div className="step-title"><span>01</span><div><h2>{t('ownerInformation')}</h2><p>{t('ownerInformationHelp')}</p></div></div><div className="form-grid">
                <Field label={t('fullName')} error={form.errors.name} required><Input autoFocus autoComplete="name" value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></Field>
                <Field label={t('email')} error={form.errors.email} required><Input type="email" autoComplete="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} /></Field>
                <Field label={t('ownerPhone')} error={form.errors.owner_phone} required><Input type="tel" value={form.data.owner_phone} onChange={e => form.setData('owner_phone', e.target.value)} /></Field>
                <div />
                <Field label={t('password')} error={form.errors.password} required><Input type="password" autoComplete="new-password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} /></Field>
                <Field label={t('confirmPassword')} error={form.errors.password_confirmation} required><Input type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} /></Field>
            </div></section>}
            {step === 2 && <section><div className="step-title"><span>02</span><div><h2>{t('businessInformation')}</h2><p>{t('businessInformationHelp')}</p></div></div><div className="form-grid">
                <Field label={t('businessName')} error={form.errors.business_name} required><Input autoFocus value={form.data.business_name} onChange={e => form.setData('business_name', e.target.value)} /></Field>
                <Field label={t('businessPhone')} error={form.errors.business_phone} required><Input type="tel" value={form.data.business_phone} onChange={e => form.setData('business_phone', e.target.value)} /></Field>
                <Field label={t('selectCity')} error={form.errors.city_id} required><Select value={form.data.city_id} onChange={e => form.setData('city_id', e.target.value)}><option value="">{t('select')}</option>{cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}</Select></Field>
                <Field label={t('language')}><Select value={form.data.preferred_language} onChange={e => form.setData('preferred_language', e.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field>
                <Field label={t('businessAddress')} error={form.errors.business_address} required><Input value={form.data.business_address} onChange={e => form.setData('business_address', e.target.value)} /></Field>
            </div></section>}
            {step === 3 && <section><div className="step-title"><span>03</span><div><h2>{t('fieldSetup')}</h2><p>{t('fieldSetupHelp')}</p></div></div><div className="form-grid">
                <Field label={t('numberOfFields')} error={form.errors.number_of_fields} required><Input type="number" min={1} max={100} value={form.data.number_of_fields} onChange={e => form.setData('number_of_fields', Number(e.target.value))} /></Field>
                <Field label={t('startingHourlyPrice')} error={form.errors.starting_price_per_hour} required><Input type="number" min={0} step="0.01" value={form.data.starting_price_per_hour} onChange={e => form.setData('starting_price_per_hour', Number(e.target.value))} /></Field>
                <Field label={t('openingTime')} error={form.errors.opening_time} required><Input type="time" value={form.data.opening_time} onChange={e => form.setData('opening_time', e.target.value)} /></Field>
                <Field label={t('closingTime')} error={form.errors.closing_time} required><Input type="time" value={form.data.closing_time} onChange={e => form.setData('closing_time', e.target.value)} /></Field>
            </div><div className="amenity-section"><h3>{t('amenities')}</h3><div className="amenity-selector">{amenityKeys.map(key => <label key={key} className={form.data.amenities.includes(key) ? 'selected' : ''}><input type="checkbox" checked={form.data.amenities.includes(key)} onChange={() => toggleAmenity(key)} /><span>{form.data.amenities.includes(key) && <Check size={15} />}{t(key)}</span></label>)}</div></div>
            <div className="review-note"><Clock3 size={20} /><div><strong>{t('reviewBeforePublishing')}</strong><p>{t('reviewBeforePublishingHelp')}</p></div></div></section>}

            <footer className="registration-actions"><div>{step > 1 && <Button type="button" variant="secondary" onClick={() => setStep(step - 1)}><ChevronLeft size={17} />{t('back')}</Button>}</div><div>{step < 3 ? <Button type="button" disabled={!canContinue} onClick={() => setStep(step + 1)}>{t('continue')}<ChevronRight size={17} /></Button> : <Button disabled={form.processing}>{t('submitApplication')}</Button>}</div></footer>
        </form><p className="registration-login">{t('alreadyHaveAccount')} <Link href="/login">{t('login')}</Link></p>
    </div></AuthLayout>;
}
