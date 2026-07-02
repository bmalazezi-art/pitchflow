import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '../lib/i18n';

export default function AuthLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const t = useTranslation();
    return <div className={wide ? 'auth-shell auth-shell-wide' : 'auth-shell'}>
        <section className={wide ? 'auth-form auth-form-wide' : 'auth-form'}>
            <Link href="/" className="brand" style={{ textDecoration: 'none', color: 'inherit' }}><span className="brand-mark">P</span><strong>PitchFlow</strong></Link>
            {children}
        </section>
        <aside className="auth-aside"><h2>{t('authTagline')}</h2></aside>
    </div>;
}
