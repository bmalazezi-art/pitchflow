import { Head, router, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import { logoutAndReplace } from '../../lib/logout';
import type { SharedProps } from '../../types';

export default function VerifyEmail({ email, canOpenDashboard }: { email: string; canOpenDashboard: boolean }) {
    const t = useTranslation();
    const { flash } = usePage<SharedProps>().props;
    const form = useForm({ email });
    const updateEmail = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch('/email', { preserveScroll: true });
    };

    return <AuthLayout><Head title={t('verifyEmail')} /><h1>{t('verifyEmail')}</h1><p>{canOpenDashboard ? t('verifyEmailOptionalIntro') : t('verifyEmailIntro')}</p>
        {flash.success && <div className="auth-callout success"><strong>{flash.success}</strong></div>}
        <div className="auth-callout"><strong>{t('checkInboxSpam')}</strong><p>{t('verificationTroubleshooting')}</p></div>
        <div className="auth-action-stack">
            {canOpenDashboard && <Button type="button" onClick={() => router.visit('/dashboard')}>{t('openDashboard')}</Button>}
            <Button type="button" variant={canOpenDashboard ? 'secondary' : 'primary'} onClick={() => router.post('/email/verification-notification')}>{t('resendVerification')}</Button>
        </div>
        <form className="auth-inline-form" onSubmit={updateEmail}>
            <Field label={t('changeEmailAddress')} error={form.errors.email}>
                <Input type="email" value={form.data.email} onChange={event => form.setData('email', event.target.value)} />
            </Field>
            <Button type="submit" variant="secondary" disabled={form.processing}>{t('updateEmail')}</Button>
        </form>
        <Button type="button" variant="secondary" onClick={logoutAndReplace}>{t('logout')}</Button>
    </AuthLayout>;
}
