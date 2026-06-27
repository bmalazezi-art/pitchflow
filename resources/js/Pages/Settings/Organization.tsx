import { Head, Link, useForm } from '@inertiajs/react';
import { Bell, Building2, CalendarClock, Clock3, Globe2, Palette } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Button, Field, Input, PageHeader, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

type SettingsTab = 'general' | 'business' | 'hours' | 'reservations' | 'notifications' | 'language' | 'branding';

export default function OrganizationSettings({ organization, cities }: { organization: any; cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [tab, setTab] = useState<SettingsTab>('general');
    const form = useForm({ name: organization.name, email: organization.email, phone: organization.phone, city_id: organization.city_id, address: organization.address, timezone: organization.timezone, currency: organization.currency, locale: organization.locale, cancellation_window_minutes: organization.cancellation_window_minutes });
    const tabs: Array<{ id: SettingsTab; label: string; icon: typeof Building2 }> = [
        { id: 'general', label: t('general'), icon: Building2 }, { id: 'business', label: t('business'), icon: Building2 },
        { id: 'hours', label: t('workingHours'), icon: Clock3 }, { id: 'reservations', label: t('reservations'), icon: CalendarClock },
        { id: 'notifications', label: t('notifications'), icon: Bell }, { id: 'language', label: t('language'), icon: Globe2 },
        { id: 'branding', label: t('branding'), icon: Palette },
    ];

    return <AppLayout title={t('settings')}><Head title={t('settings')} /><div className="owner-page settings-page">
        <PageHeader eyebrow={t('workspace')} title={t('organizationSettings')} description={t('settingsIntro')} />
        <div className="settings-layout"><nav className="settings-tabs" aria-label={t('settings')}>{tabs.map(({ id, label, icon: Icon }) => <button key={id} className={tab === id ? 'active' : ''} onClick={() => setTab(id)}><Icon size={17} /><span>{label}</span></button>)}</nav>
            <section className="dashboard-panel settings-panel"><form onSubmit={event => { event.preventDefault(); form.put('/settings/organization'); }}>
                {tab === 'general' && <><div className="settings-section-title"><h2>{t('general')}</h2><p>{t('generalSettingsIntro')}</p></div><div className="form-grid"><Field label={t('businessName')} error={form.errors.name}><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></Field><Field label={t('businessEmail')} error={form.errors.email}><Input type="email" value={form.data.email} onChange={event => form.setData('email', event.target.value)} /></Field><Field label={t('phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={event => form.setData('phone', event.target.value)} /></Field><Field label={t('selectCity')}><Select value={form.data.city_id} onChange={event => form.setData('city_id', Number(event.target.value))}>{cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}</Select></Field><Field label={t('address')} error={form.errors.address}><Input value={form.data.address} onChange={event => form.setData('address', event.target.value)} /></Field><Field label={t('timezone')} error={form.errors.timezone}><Input value={form.data.timezone} onChange={event => form.setData('timezone', event.target.value)} /></Field></div></>}
                {tab === 'business' && <><div className="settings-section-title"><h2>{t('business')}</h2><p>{t('businessSettingsIntro')}</p></div><div className="form-grid"><Field label={t('currency')} error={form.errors.currency}><Input maxLength={3} value={form.data.currency} onChange={event => form.setData('currency', event.target.value.toUpperCase())} /></Field><Field label={t('businessAddress')} error={form.errors.address}><Input value={form.data.address} onChange={event => form.setData('address', event.target.value)} /></Field></div></>}
                {tab === 'hours' && <div className="settings-empty"><span><Clock3 size={23} /></span><h2>{t('workingHours')}</h2><p>{t('workingHoursSettingsIntro')}</p><Link className="btn btn-primary" href="/fields">{t('manageFieldHours')}</Link></div>}
                {tab === 'reservations' && <><div className="settings-section-title"><h2>{t('reservationSettings')}</h2><p>{t('reservationSettingsIntro')}</p></div><div className="form-grid one-column"><Field label={t('cancellationWindow')} error={form.errors.cancellation_window_minutes}><Input type="number" min="0" value={form.data.cancellation_window_minutes} onChange={event => form.setData('cancellation_window_minutes', Number(event.target.value))} /></Field></div></>}
                {tab === 'notifications' && <><div className="settings-section-title"><h2>{t('notifications')}</h2><p>{t('notificationSettingsIntro')}</p></div><div className="setting-toggles"><label><span><strong>{t('emailNotifications')}</strong><small>{t('reservationActivity')}</small></span><input type="checkbox" defaultChecked disabled /></label><label><span><strong>{t('smsNotifications')}</strong><small>{t('futureFeature')}</small></span><input type="checkbox" disabled /></label></div></>}
                {tab === 'language' && <><div className="settings-section-title"><h2>{t('language')}</h2><p>{t('languageSettingsIntro')}</p></div><div className="form-grid one-column"><Field label={t('language')}><Select value={form.data.locale} onChange={event => form.setData('locale', event.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field></div></>}
                {tab === 'branding' && <><div className="settings-section-title"><h2>{t('branding')}</h2><p>{t('brandingSettingsIntro')}</p></div><div className="form-grid"><Field label={t('logo')}><Input type="file" disabled /></Field><Field label={t('primaryColor')}><Input type="color" value="#2563eb" disabled readOnly /></Field><Field label={t('secondaryColor')}><Input type="color" value="#16a34a" disabled readOnly /></Field></div></>}
                {!['hours', 'notifications', 'branding'].includes(tab) && <div className="form-actions"><Button disabled={form.processing}>{t('saveChanges')}</Button></div>}
            </form></section></div>
    </div></AppLayout>;
}
