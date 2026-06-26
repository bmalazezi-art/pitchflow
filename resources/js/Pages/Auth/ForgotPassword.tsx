import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    const t = useTranslation();
    return <AuthLayout><Head title={t('resetPassword')} /><h1>{t('resetPassword')}</h1><p>{t('resetIntro')}</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }}>
            <Field label={t('email')} error={form.errors.email} required><Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Button disabled={form.processing}>{t('sendResetLink')}</Button><Link href="/login">{t('backToLogin')}</Link>
        </form>
    </AuthLayout>;
}
