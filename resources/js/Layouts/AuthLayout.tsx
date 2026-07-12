import { Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '../lib/i18n';

export default function AuthLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const t = useTranslation();

    return <div className={wide ? 'auth-shell auth-shell-wide' : 'auth-shell'}>
        <section className={wide ? 'auth-form auth-form-wide' : 'auth-form'}>
            <Link href="/" className="brand" style={{ textDecoration: 'none', color: 'inherit' }}><span className="brand-mark">P</span><strong>PitchFlow</strong></Link>
            {children}
        </section>
        <aside className="auth-aside">
            <div className="auth-visual-panel">
                <span className="auth-visual-kicker"><BadgeCheck size={16} />{t('verifiedFields')}</span>
                <h2>{t('authTagline')}</h2>
                <p>{t('authVisualIntro')}</p>
                <div className="auth-pitch-visual" aria-hidden="true">
                    <div className="auth-pitch-lines" />
                    <div className="auth-slot-card slot-one"><span>18:00</span><strong>{t('available')}</strong></div>
                    <div className="auth-slot-card slot-two"><span>20:00</span><strong>{t('reserved')}</strong></div>
                </div>
            </div>
        </aside>
    </div>;
}
