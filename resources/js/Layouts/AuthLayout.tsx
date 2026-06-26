import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '../lib/i18n';

export default function AuthLayout({ children }: { children: ReactNode }) {
    const t = useTranslation();
    return <div className="auth-shell">
        <section className="auth-form">
            <Link href="/" className="brand" style={{ textDecoration: 'none', color: 'inherit' }}><span className="brand-mark">P</span><strong>PitchFlow</strong></Link>
            {children}
        </section>
        <aside className="auth-aside"><h2>{t('authTagline')}</h2></aside>
    </div>;
}
