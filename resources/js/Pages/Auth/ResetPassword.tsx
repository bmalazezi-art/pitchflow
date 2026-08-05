import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ email, token, password: '', password_confirmation: '' });
    const t = useTranslation();
    const passwordsMismatch = form.data.password_confirmation.length > 0 && form.data.password !== form.data.password_confirmation;
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (form.processing) return;
        if (form.data.password !== form.data.password_confirmation) {
            form.setError('password_confirmation', t('passwordsDoNotMatch'));
            return;
        }
        form.post('/reset-password');
    };
    return <AuthLayout><Head title={t('choosePassword')} /><h1>{t('choosePassword')}</h1><p>{t('choosePasswordIntro')}</p>
        <form onSubmit={submit}>
            <Field label={t('email')} error={form.errors.email}><Input type="email" value={form.data.email} readOnly /></Field>
            <Field label={t('newPassword')} error={form.errors.password}><Input type="password" value={form.data.password} onChange={(e) => {
                form.setData('password', e.target.value);
                if (form.errors.password_confirmation === t('passwordsDoNotMatch')) form.clearErrors('password_confirmation');
            }} /></Field>
            <Field label={t('confirmPassword')} error={form.errors.password_confirmation || (passwordsMismatch ? t('passwordsDoNotMatch') : undefined)}><Input type="password" value={form.data.password_confirmation} onChange={(e) => {
                form.setData('password_confirmation', e.target.value);
                if (form.errors.password_confirmation === t('passwordsDoNotMatch')) form.clearErrors('password_confirmation');
            }} /></Field>
            <Button disabled={form.processing || passwordsMismatch}>{form.processing ? t('processing') : t('resetPassword')}</Button>
        </form>
    </AuthLayout>;
}
