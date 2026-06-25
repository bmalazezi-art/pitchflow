import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Button, Field, Input, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function OrganizationSettings({ organization, cities }: { organization: any; cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation(); const form = useForm({ name: organization.name, email: organization.email, phone: organization.phone, city_id: organization.city_id, address: organization.address, timezone: organization.timezone, currency: organization.currency, locale: organization.locale, cancellation_window_minutes: organization.cancellation_window_minutes });
    return <AppLayout title={t('settings')}><Head title={t('settings')} /><div className="page-header"><div><h1>Organization settings</h1><p>Business identity, locale, timezone, and reservation policy.</p></div></div>
        <section className="panel" style={{ maxWidth: 820 }}><form onSubmit={e => { e.preventDefault(); form.put('/settings/organization'); }}><div className="form-grid">
            <Field label="Business name" error={form.errors.name}><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></Field><Field label="Business email" error={form.errors.email}><Input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} /></Field>
            <Field label={t('phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} /></Field><Field label={t('selectCity')}><Select value={form.data.city_id} onChange={e => form.setData('city_id', Number(e.target.value))}>{cities.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
            <Field label="Address" error={form.errors.address}><Input value={form.data.address} onChange={e => form.setData('address', e.target.value)} /></Field><Field label="Timezone" error={form.errors.timezone}><Input value={form.data.timezone} onChange={e => form.setData('timezone', e.target.value)} /></Field>
            <Field label="Currency" error={form.errors.currency}><Input maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} /></Field><Field label={t('language')}><Select value={form.data.locale} onChange={e => form.setData('locale', e.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field>
            <Field label="Late cancellation window (minutes)" error={form.errors.cancellation_window_minutes}><Input type="number" min="0" value={form.data.cancellation_window_minutes} onChange={e => form.setData('cancellation_window_minutes', Number(e.target.value))} /></Field>
        </div><div className="form-actions"><Button disabled={form.processing}>{t('save')}</Button></div></form></section>
    </AppLayout>;
}
