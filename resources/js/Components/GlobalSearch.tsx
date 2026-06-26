import { Link } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState, Input } from './UI';
import { useTranslation } from '../lib/i18n';

export default function GlobalSearch({ open, onClose }: { open: boolean; onClose: () => void }) {
    const t = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<any>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults({});
            return;
        }
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                setResults(await response.json());
            } finally {
                setLoading(false);
            }
        }, 200);
        return () => { window.clearTimeout(timeout); controller.abort(); };
    }, [query]);

    if (!open) return null;
    const groups = Object.entries(results).filter(([, items]) => Array.isArray(items) && items.length);
    const href = (type: string, item: any) => type === 'customers'
        ? `/customers/${item.id}`
        : type === 'reservations'
            ? '/reservations'
            : type === 'fields'
                ? '/fields'
                : type === 'organizations'
                    ? '/admin/organizations'
                    : '/employees';
    return <div className="modal-backdrop" onMouseDown={event => event.target === event.currentTarget && onClose()}>
        <section className="modal" role="dialog" aria-modal="true" aria-label={t('liveSearch')}>
            <header><h2><Search size={18} /> {t('liveSearch')}</h2><button className="icon-btn" onClick={onClose} aria-label={t('close')}><X size={20} /></button></header>
            <Input autoFocus placeholder={t('searchPlaceholder')} value={query} onChange={event => setQuery(event.target.value)} />
            <div style={{ marginTop: 14 }}>
                {loading && <p style={{ color: 'var(--muted)' }}>{t('searching')}</p>}
                {!loading && query.length >= 2 && groups.length === 0 && <EmptyState title={t('noResults')} />}
                {groups.map(([type, items]: [string, any]) => <section key={type}><h3 style={{ textTransform: 'capitalize' }}>{type}</h3>{items.map((item: any) => <Link key={item.id} href={href(type, item)} onClick={onClose} style={{ display: 'block', padding: '10px 0', borderTop: '1px solid var(--border)', color: 'inherit', textDecoration: 'none' }}><strong>{item.name ?? item.customer_name}</strong><small style={{ display: 'block', color: 'var(--muted)' }}>{item.phone ?? item.customer_phone ?? item.email ?? ''}</small></Link>)}</section>)}
            </div>
        </section>
    </div>;
}
