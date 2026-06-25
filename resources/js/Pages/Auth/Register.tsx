import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Register({ cities }: { cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '', business_name: '', phone: '', city_id: '', business_address: '', number_of_fields: 1, preferred_language: 'en' });
    return <AuthLayout><Head title={t('register')} /><h1>{t('register')}</h1><p>Applications are reviewed before workspace access is enabled.</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/register'); }}>
            <div className="form-grid">
                <Field label="Full name" error={form.errors.name} required><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
                <Field label={t('email')} error={form.errors.email} required><Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
                <Field label="Business name" error={form.errors.business_name} required><Input value={form.data.business_name} onChange={(e) => form.setData('business_name', e.target.value)} /></Field>
                <Field label={t('phone')} error={form.errors.phone} required><Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></Field>
                <Field label={t('selectCity')} error={form.errors.city_id} required><Select value={form.data.city_id} onChange={(e) => form.setData('city_id', e.target.value)}><option value="">Select…</option>{cities.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
                <Field label="Number of fields" error={form.errors.number_of_fields} required><Input type="number" min={1} value={form.data.number_of_fields} onChange={(e) => form.setData('number_of_fields', Number(e.target.value))} /></Field>
                <Field label="Business address" error={form.errors.business_address} required><Input value={form.data.business_address} onChange={(e) => form.setData('business_address', e.target.value)} /></Field>
                <Field label={t('language')}><Select value={form.data.preferred_language} onChange={(e) => form.setData('preferred_language', e.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field>
                <Field label={t('password')} error={form.errors.password} required><Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
                <Field label="Confirm password" error={form.errors.password_confirmation} required><Input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} /></Field>
            </div>
            <Button disabled={form.processing}>{t('register')}</Button><Link href="/login">{t('login')}</Link>
        </form>
    </AuthLayout>;
}
