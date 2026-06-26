import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ email, token, password: '', password_confirmation: '' });
    const t = useTranslation();
    return <AuthLayout><Head title={t('choosePassword')} /><h1>{t('choosePassword')}</h1><p>{t('choosePasswordIntro')}</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/reset-password'); }}>
            <Field label={t('email')} error={form.errors.email}><Input type="email" value={form.data.email} readOnly /></Field>
            <Field label={t('newPassword')} error={form.errors.password}><Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <Field label={t('confirmPassword')}><Input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} /></Field>
            <Button disabled={form.processing}>{t('resetPassword')}</Button>
        </form>
    </AuthLayout>;
}
