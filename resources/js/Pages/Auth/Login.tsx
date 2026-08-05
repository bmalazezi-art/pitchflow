import { Head, Link, useForm } from '@inertiajs/react';
import { LogIn, ShieldCheck } from 'lucide-react';
import { useEffect } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Login() {
    const t = useTranslation();
    const form = useForm({ email: '', password: '', remember: false });

    useEffect(() => {
        const clearPassword = (event?: PageTransitionEvent) => {
            if (! event || event.persisted) {
                form.setData('password', '');
            }
        };

        clearPassword();
        window.addEventListener('pageshow', clearPassword);

        return () => window.removeEventListener('pageshow', clearPassword);
    }, []);

    return <AuthLayout><Head title={t('login')} />
        <Link href="/" className="auth-back-home">← {t('backToHome')}</Link>
        <div className="auth-card auth-login-card">
            <span className="auth-kicker"><ShieldCheck size={16} />{t('secureWorkspaceAccess')}</span>
            <h1>{t('login')}</h1>
            <p>{t('accessWorkspace')}</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/login'); }}>
            <Field label={t('emailOrPhone')} error={form.errors.email} required><Input type="text" autoComplete="username" required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Field label={t('password')} error={form.errors.password} required><Input type="password" autoComplete="current-password" required value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <div className="auth-form-options">
                <label><input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} /> {t('rememberMe')}</label>
                <Link href="/forgot-password">{t('forgotPassword')}</Link>
            </div>
            <Button className="auth-submit" disabled={form.processing}><LogIn size={17} />{form.processing ? t('processing') : t('login')}</Button>
            <div className="auth-switch-link">{t('ownFootballField')} <Link href="/register">{t('register')}</Link></div>
        </form>
        </div>
    </AuthLayout>;
}
