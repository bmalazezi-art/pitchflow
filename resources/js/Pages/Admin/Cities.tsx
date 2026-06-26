import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Field, Input, Modal } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function Cities({ cities }: { cities: any[] }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', country: 'XK' });
    return <AppLayout title={t('cities')}><Head title={t('cities')} /><div className="page-header"><div><h1>{t('cities')}</h1><p>{t('citiesIntro')}</p></div><Button onClick={() => setOpen(true)}><Plus size={18} />{t('addCity')}</Button></div>
        <div className="table-wrap"><table><thead><tr><th>{t('name')}</th><th>{t('country')}</th><th>{t('status')}</th><th>{t('organizations')}</th><th>{t('fields')}</th><th>{t('actions')}</th></tr></thead><tbody>{cities.map(city => <tr key={city.id}><td><strong>{city.name}</strong></td><td>{city.country}</td><td><Badge value={city.is_active ? 'active' : 'closed'} /></td><td>{city.organizations_count}</td><td>{city.football_fields_count}</td><td><Button variant="secondary" onClick={() => router.put(`/admin/cities/${city.id}`, { name: city.name, country: city.country, is_active: !city.is_active })}>{city.is_active ? t('disable') : t('enable')}</Button></td></tr>)}</tbody></table></div>
        <Modal open={open} title={t('addCity')} onClose={() => setOpen(false)}><form onSubmit={e => { e.preventDefault(); form.post('/admin/cities', { onSuccess: () => { setOpen(false); form.reset(); } }); }}><div className="form-grid"><Field label={t('name')} error={form.errors.name}><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></Field><Field label={t('countryCode')} error={form.errors.country}><Input maxLength={2} value={form.data.country} onChange={e => form.setData('country', e.target.value.toUpperCase())} /></Field></div><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button><Button disabled={form.processing}>{t('save')}</Button></div></form></Modal>
    </AppLayout>;
}
