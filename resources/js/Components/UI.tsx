import { Link } from '@inertiajs/react';
import { AlertCircle, Search, X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import clsx from 'clsx';
import { useTranslation } from '../lib/i18n';

export function Button({ children, variant = 'primary', className, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' | 'success' }) {
    return <button className={clsx('btn', `btn-${variant}`, className)} {...props}>{children}</button>;
}

export function Field({ label, error, required, children }: { label: string; error?: string; required?: boolean; children: ReactNode }) {
    return <label className="field-label"><span>{label}{required && <b aria-hidden="true"> *</b>}</span>{children}{error && <small className="field-error"><AlertCircle size={14} />{error}</small>}</label>;
}

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="input" {...props} />;
}

export function PriceInput(props: Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'>) {
    return <div className="price-input-group">
        <span aria-hidden="true">€</span>
        <input
            className="input"
            inputMode="decimal"
            pattern="^[0-9]+([.,][0-9]{1,2})?$"
            placeholder="p.sh. 30"
            {...props}
        />
        <span aria-hidden="true">/h</span>
    </div>;
}

export function SearchInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <label className="search-input"><Search size={17} /><input {...props} /></label>;
}

export function Select(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
    return <select className="input" {...props} />;
}

export function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
    const t = useTranslation();
    if (!open) return null;
    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <section className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <header><h2 id="modal-title">{title}</h2><button className="icon-btn" onClick={onClose} aria-label={t('close')}><X size={20} /></button></header>
            {children}
        </section>
    </div>;
}

export function Drawer({ open, title, subtitle, onClose, children, footer }: { open: boolean; title: string; subtitle?: string; onClose: () => void; children: ReactNode; footer?: ReactNode }) {
    const t = useTranslation();
    useEffect(() => {
        if (!open) return;
        const closeOnEscape = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [onClose, open]);
    if (!open) return null;

    return <div className="drawer-backdrop" role="presentation" onMouseDown={event => event.target === event.currentTarget && onClose()}>
        <aside className="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
            <header className="drawer-header"><div><h2 id="drawer-title">{title}</h2>{subtitle && <p>{subtitle}</p>}</div><button className="icon-btn" onClick={onClose} aria-label={t('close')}><X size={20} /></button></header>
            <div className="drawer-body">{children}</div>
            {footer && <footer className="drawer-footer">{footer}</footer>}
        </aside>
    </div>;
}

export function PageHeader({ eyebrow, title, description, actions }: { eyebrow?: string; title: string; description: string; actions?: ReactNode }) {
    return <header className="owner-page-header"><div>{eyebrow && <span>{eyebrow}</span>}<h1>{title}</h1><p>{description}</p></div>{actions && <div className="owner-page-actions">{actions}</div>}</header>;
}

export function Badge({ value }: { value: string }) {
    const t = useTranslation();
    const key = ({
        reliable: 'reliable', needs_attention: 'needsAttention', high_risk: 'highRisk',
        pending: 'pending', confirmed: 'confirmed', completed: 'completed',
        cancelled: 'cancelled', late_cancelled: 'lateCancelled', no_show: 'noShow', voided: 'voided',
        unpaid: 'unpaid', partial: 'partial', paid: 'paid',
        active: 'active', maintenance: 'maintenance', closed: 'closed',
        approved: 'approved', rejected: 'rejected', suspended: 'suspended',
        available: 'available', occupied: 'occupied', past: 'past',
        trial: 'trial', expired: 'expired',
        invited: 'invited', disabled: 'disabled',
    } as Record<string, any>)[value];

    return <span className={clsx('badge', `badge-${value}`)}>{key ? t(key) : value.replaceAll('_', ' ')}</span>;
}

export function EmptyState({ title, action }: { title: string; action?: ReactNode }) {
    return <div className="empty"><p>{title}</p>{action}</div>;
}

export function Pagination({ links }: { links: Array<{ url: string | null; label: string; active: boolean }> }) {
    const t = useTranslation();
    return <nav className="pagination" aria-label={t('pagination')}>{links.map((link, index) =>
        link.url ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
            : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />
    )}</nav>;
}
