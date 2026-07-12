import { Head, Link, useForm } from '@inertiajs/react';
import { LogIn, ShieldCheck } from 'lucide-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Login() {
    const t = useTranslation();
    const form = useForm({ email: '', password: '', remember: false });
    return <AuthLayout><Head title={t('login')} />
        <div className="auth-card auth-login-card">
            <span className="auth-kicker"><ShieldCheck size={16} />{t('secureWorkspaceAccess')}</span>
            <h1>{t('login')}</h1>
            <p>{t('accessWorkspace')}</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/login'); }}>
            <Field label={t('email')} error={form.errors.email} required><Input type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Field label={t('password')} error={form.errors.password} required><Input type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <div className="auth-form-options">
                <label><input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} /> {t('rememberMe')}</label>
                <Link href="/forgot-password">{t('forgotPassword')}</Link>
            </div>
            <Button className="auth-submit" disabled={form.processing}><LogIn size={17} />{t('login')}</Button>
            <div className="auth-switch-link">{t('ownFootballField')} <Link href="/register">{t('register')}</Link></div>
        </form>
        </div>
    </AuthLayout>;
}
