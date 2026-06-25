import { Link } from '@inertiajs/react';
import { AlertCircle, X } from 'lucide-react';
import type { ReactNode } from 'react';
import clsx from 'clsx';

export function Button({ children, variant = 'primary', className, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' | 'success' }) {
    return <button className={clsx('btn', `btn-${variant}`, className)} {...props}>{children}</button>;
}

export function Field({ label, error, required, children }: { label: string; error?: string; required?: boolean; children: ReactNode }) {
    return <label className="field-label"><span>{label}{required && <b aria-hidden="true"> *</b>}</span>{children}{error && <small className="field-error"><AlertCircle size={14} />{error}</small>}</label>;
}

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="input" {...props} />;
}

export function Select(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
    return <select className="input" {...props} />;
}

export function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
    if (!open) return null;
    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <section className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <header><h2 id="modal-title">{title}</h2><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20} /></button></header>
            {children}
        </section>
    </div>;
}

export function Badge({ value }: { value: string }) {
    return <span className={clsx('badge', `badge-${value}`)}>{value.replaceAll('_', ' ')}</span>;
}

export function EmptyState({ title, action }: { title: string; action?: ReactNode }) {
    return <div className="empty"><p>{title}</p>{action}</div>;
}

export function Pagination({ links }: { links: Array<{ url: string | null; label: string; active: boolean }> }) {
    return <nav className="pagination" aria-label="Pagination">{links.map((link, index) =>
        link.url ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
            : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />
    )}</nav>;
}
