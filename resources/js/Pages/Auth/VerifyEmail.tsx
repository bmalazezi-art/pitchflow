import { Head, router } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function VerifyEmail() {
    const t = useTranslation();
    return <AuthLayout><Head title={t('verifyEmail')} /><h1>{t('verifyEmail')}</h1><p>{t('verifyEmailIntro')}</p>
        <Button onClick={() => router.post('/email/verification-notification')}>{t('resendVerification')}</Button>
        <Button variant="secondary" onClick={() => router.post('/logout')}>{t('logout')}</Button>
    </AuthLayout>;
}
