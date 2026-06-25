import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { Badge, Button, Input, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

interface Props {
    cities: Array<{ id: number; name: string }>;
    fields: Array<{ id: number; name: string; address?: string; organization: { name: string } }>;
    selectedField?: { name: string; organization: { name: string } };
    slots: Array<{ starts_at: string; ends_at: string; label: string; status: string }>;
    filters: { city?: number; field?: number; date: string };
}

export default function Availability({ cities, fields, selectedField, slots, filters }: Props) {
    const t = useTranslation();
    const update = (key: string, value: string) => router.get('/', { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    return <div className="public-page"><Head title={t('checkAvailability')} />
        <nav className="public-nav"><div className="brand"><span className="brand-mark">P</span><strong>PitchFlow</strong></div><div><Link href="/login" className="btn btn-secondary">{t('login')}</Link> <Link href="/register" className="btn btn-primary">{t('register')}</Link></div></nav>
        <main className="public-content"><CalendarDays size={34} color="#2563eb" /><h1>{t('checkAvailability')}</h1><p>Live one-hour availability from verified football field businesses.</p>
            <div className="availability-controls">
                <Select aria-label={t('selectCity')} value={filters.city ?? ''} onChange={(e) => update('city', e.target.value)}><option value="">{t('selectCity')}</option>{cities.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</Select>
                <Select aria-label={t('selectField')} value={filters.field ?? ''} onChange={(e) => update('field', e.target.value)}><option value="">{t('selectField')}</option>{fields.map(f => <option key={f.id} value={f.id}>{f.organization.name} · {f.name}</option>)}</Select>
                <Input aria-label={t('chooseDate')} type="date" value={filters.date} onChange={(e) => update('date', e.target.value)} />
                <Button onClick={() => router.reload()}>{t('checkAvailability')}</Button>
            </div>
            {selectedField && <div className="page-header" style={{ marginTop: 28 }}><div><h2>{selectedField.organization.name} · {selectedField.name}</h2><p>Only slot availability is shown. Customer details remain private.</p></div></div>}
            <div className="slots">{slots.map(slot => <div className={`slot ${slot.status}`} key={slot.starts_at}><strong>{slot.label}</strong><Badge value={slot.status} /></div>)}</div>
        </main>
    </div>;
}
