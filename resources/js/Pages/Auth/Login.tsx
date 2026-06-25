import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Login() {
    const t = useTranslation();
    const form = useForm({ email: '', password: '', remember: false });
    return <AuthLayout><Head title={t('login')} /><h1>{t('login')}</h1><p>Access your reservation workspace.</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/login'); }}>
            <Field label={t('email')} error={form.errors.email} required><Input type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Field label={t('password')} error={form.errors.password} required><Input type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <label><input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} /> Remember me</label>
            <Button disabled={form.processing}>{t('login')}</Button>
            <div><Link href="/forgot-password">Forgot password?</Link> · <Link href="/register">{t('register')}</Link></div>
        </form>
    </AuthLayout>;
}
