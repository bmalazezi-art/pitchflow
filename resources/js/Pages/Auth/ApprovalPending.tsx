import { Head, router, usePage } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Badge } from '../../Components/UI';
import type { SharedProps } from '../../types';
import { useTranslation } from '../../lib/i18n';

export default function ApprovalPending() {
    const { auth } = usePage<SharedProps>().props;
    const t = useTranslation();
    const status = auth.organization?.status ?? 'pending';
    return <AuthLayout><Head title={t('applicationStatus')} /><Badge value={status} /><h1 style={{ marginTop: 16 }}>{t('applicationStatus')}: {status.replace('_', ' ')}</h1>
        <p>{t('approvalIntro')}</p>
        {status === 'approved' && <Button onClick={() => router.visit('/dashboard')}>{t('openDashboard')}</Button>}
        <Button variant="secondary" onClick={() => router.post('/logout')}>{t('logout')}</Button>
    </AuthLayout>;
}
