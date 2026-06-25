import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AuthLayout({ children }: { children: ReactNode }) {
    return <div className="auth-shell">
        <section className="auth-form">
            <Link href="/" className="brand" style={{ textDecoration: 'none', color: 'inherit' }}><span className="brand-mark">P</span><strong>PitchFlow</strong></Link>
            {children}
        </section>
        <aside className="auth-aside"><h2>Less time scheduling. More time running your fields.</h2></aside>
    </div>;
}
