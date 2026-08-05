import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    const { flash } = usePage<SharedProps>().props;
    const [copied, setCopied] = useState(false);
    const t = useTranslation();
    const resetUrl = flash.reset_url ?? flash.reset_link;
    const copyResetLink = async () => {
        if (!resetUrl) return;
        await navigator.clipboard.writeText(resetUrl);
        setCopied(true);
    };

    return <AuthLayout><Head title={t('resetPassword')} /><h1>{t('resetPassword')}</h1><p>{t('resetIntro')}</p>
        {flash.success && <div className="auth-callout success"><strong>{flash.success}</strong></div>}
        {resetUrl && <div className="auth-callout reset-link-callout">
            {flash.reset_notice && <strong>{flash.reset_notice}</strong>}
            <p>{resetUrl}</p>
            <Button type="button" variant="secondary" onClick={copyResetLink}><Copy size={16} />{t('copyResetLink')}</Button>
            {copied && <small>{t('resetLinkCopied')}</small>}
        </div>}
        <form onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }}>
            <Field label={t('emailOrPhone')} error={form.errors.email} required><Input type="text" autoComplete="username" placeholder="example@email.com or +383 44 123 456" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Button disabled={form.processing}>{form.processing ? t('processing') : t('sendResetLink')}</Button><Link href="/login">{t('backToLogin')}</Link>
        </form>
    </AuthLayout>;
}
