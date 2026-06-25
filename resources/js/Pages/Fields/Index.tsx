import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, EmptyState, Field, Input, Modal, Pagination, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

export default function Fields({ fields, cities }: { fields: Paginated<any>; cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const form = useForm({ name: '', city_id: '', address: '', status: 'active', price_per_hour: 0, opening_time: '12:00', closing_time: '01:00' });
    const show = (field?: any) => { setEditing(field ?? null); form.setData(field ? { name: field.name, city_id: field.city_id ?? '', address: field.address ?? '', status: field.status, price_per_hour: field.price_per_hour, opening_time: field.opening_time.slice(0, 5), closing_time: field.closing_time.slice(0, 5) } : { name: '', city_id: '', address: '', status: 'active', price_per_hour: 0, opening_time: '12:00', closing_time: '01:00' }); setOpen(true); };
    const submit = (e: React.FormEvent) => { e.preventDefault(); editing ? form.put(`/fields/${editing.id}`, { onSuccess: () => setOpen(false) }) : form.post('/fields', { onSuccess: () => setOpen(false) }); };
    return <AppLayout title={t('fields')}><Head title={t('fields')} /><div className="page-header"><div><h1>{t('fields')}</h1><p>Pricing, status, and operating hours for each pitch.</p></div><Button onClick={() => show()}><Plus size={18} />{t('newField')}</Button></div>
        {fields.data.length === 0 ? <div className="panel"><EmptyState title="No football fields have been created yet." action={<Button onClick={() => show()}>{t('newField')}</Button>} /></div> : <><div className="table-wrap"><table><thead><tr><th>{t('name')}</th><th>{t('status')}</th><th>Price / hour</th><th>Reservations</th><th>Assigned staff</th><th>{t('actions')}</th></tr></thead><tbody>{fields.data.map(field => <tr key={field.id}><td><strong>{field.name}</strong><br /><small>{field.address}</small></td><td><Badge value={field.status} /></td><td>€{field.price_per_hour}</td><td>{field.reservations_count}</td><td>{field.employees_count}</td><td><div className="actions"><button className="icon-btn" onClick={() => show(field)} title={t('edit')}><Pencil size={17} /></button><button className="icon-btn" onClick={() => confirm('Remove this field?') && router.delete(`/fields/${field.id}`)} title={t('delete')}><Trash2 size={17} /></button></div></td></tr>)}</tbody></table></div><Pagination links={fields.links} /></>}
        <Modal open={open} title={editing ? 'Edit football field' : t('newField')} onClose={() => setOpen(false)}><form onSubmit={submit}><div className="form-grid">
            <Field label={t('name')} error={form.errors.name} required><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></Field>
            <Field label={t('selectCity')} error={form.errors.city_id}><Select value={form.data.city_id} onChange={e => form.setData('city_id', e.target.value)}><option value="">Organization city</option>{cities.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
            <Field label="Address" error={form.errors.address}><Input value={form.data.address} onChange={e => form.setData('address', e.target.value)} /></Field>
            <Field label={t('status')} error={form.errors.status}><Select value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="active">Active</option><option value="maintenance">Maintenance</option><option value="closed">Closed</option></Select></Field>
            <Field label="Price per hour" error={form.errors.price_per_hour}><Input type="number" min="0" step="0.01" value={form.data.price_per_hour} onChange={e => form.setData('price_per_hour', Number(e.target.value))} /></Field>
            <div />
            <Field label="Opening time" error={form.errors.opening_time}><Input type="time" value={form.data.opening_time} onChange={e => form.setData('opening_time', e.target.value)} /></Field>
            <Field label="Closing time" error={form.errors.closing_time}><Input type="time" value={form.data.closing_time} onChange={e => form.setData('closing_time', e.target.value)} /></Field>
        </div><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button><Button disabled={form.processing}>{t('save')}</Button></div></form></Modal>
    </AppLayout>;
}
