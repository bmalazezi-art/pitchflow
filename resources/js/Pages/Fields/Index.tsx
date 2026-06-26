import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, EmptyState, Field, Input, Modal, Pagination, Select } from '../../Components/UI';
import AppLayout from '../../Layouts/AppLayout';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

interface OperatingHour {
    day_of_week: number;
    opening_time: string;
    closing_time: string;
    is_closed: boolean;
}

interface FieldForm {
    name: string;
    city_id: number | string;
    address: string;
    status: string;
    price_per_hour: number;
    opening_time: string;
    closing_time: string;
    operating_hours: OperatingHour[];
}

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const defaultHours = (): OperatingHour[] => dayNames.map((_, day) => ({
    day_of_week: day,
    opening_time: '12:00',
    closing_time: '01:00',
    is_closed: false,
}));

export default function Fields({ fields, cities }: { fields: Paginated<any>; cities: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const form = useForm<FieldForm>({
        name: '',
        city_id: '',
        address: '',
        status: 'active',
        price_per_hour: 0,
        opening_time: '12:00',
        closing_time: '01:00',
        operating_hours: defaultHours(),
    });

    const show = (field?: any) => {
        setEditing(field ?? null);
        const hours = defaultHours().map(defaultHour => {
            const saved = field?.operating_hours?.find((hour: OperatingHour) => hour.day_of_week === defaultHour.day_of_week);
            return saved ? {
                ...saved,
                opening_time: saved.opening_time.slice(0, 5),
                closing_time: saved.closing_time.slice(0, 5),
            } : defaultHour;
        });
        form.setData(field ? {
            name: field.name,
            city_id: field.city_id ?? '',
            address: field.address ?? '',
            status: field.status,
            price_per_hour: Number(field.price_per_hour),
            opening_time: field.opening_time.slice(0, 5),
            closing_time: field.closing_time.slice(0, 5),
            operating_hours: hours,
        } : {
            name: '',
            city_id: '',
            address: '',
            status: 'active',
            price_per_hour: 0,
            opening_time: '12:00',
            closing_time: '01:00',
            operating_hours: defaultHours(),
        });
        setOpen(true);
    };

    const updateHour = (index: number, changes: Partial<OperatingHour>) => {
        form.setData('operating_hours', form.data.operating_hours.map((hour, hourIndex) => (
            hourIndex === index ? { ...hour, ...changes } : hour
        )));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (editing) {
            form.put(`/fields/${editing.id}`, { onSuccess: () => setOpen(false) });
        } else {
            form.post('/fields', { onSuccess: () => setOpen(false) });
        }
    };

    return <AppLayout title={t('fields')}>
        <Head title={t('fields')} />
        <div className="page-header">
            <div><h1>{t('fields')}</h1><p>{t('fieldsIntro')}</p></div>
            <Button onClick={() => show()}><Plus size={18} />{t('newField')}</Button>
        </div>

        {fields.data.length === 0
            ? <div className="panel"><EmptyState title={t('noFields')} action={<Button onClick={() => show()}>{t('newField')}</Button>} /></div>
            : <>
                <div className="table-wrap"><table><thead><tr>
                    <th>{t('name')}</th><th>{t('status')}</th><th>{t('pricePerHour')}</th>
                    <th>{t('reservations')}</th><th>{t('assignedStaff')}</th><th>{t('actions')}</th>
                </tr></thead><tbody>{fields.data.map(field => <tr key={field.id}>
                    <td><strong>{field.name}</strong><br /><small>{field.address}</small></td>
                    <td><Badge value={field.status} /></td><td>€{field.price_per_hour}</td>
                    <td>{field.reservations_count}</td><td>{field.employees_count}</td>
                    <td><div className="actions">
                        <button className="icon-btn" onClick={() => show(field)} title={t('edit')}><Pencil size={17} /></button>
                        <button className="icon-btn" onClick={() => confirm(t('removeFieldConfirm')) && router.delete(`/fields/${field.id}`)} title={t('delete')}><Trash2 size={17} /></button>
                    </div></td>
                </tr>)}</tbody></table></div>
                <Pagination links={fields.links} />
            </>}

        <Modal open={open} title={editing ? t('editField') : t('newField')} onClose={() => setOpen(false)}>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <Field label={t('name')} error={form.errors.name} required><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></Field>
                    <Field label={t('selectCity')} error={form.errors.city_id}><Select value={form.data.city_id} onChange={event => form.setData('city_id', event.target.value)}><option value="">{t('organizationCity')}</option>{cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}</Select></Field>
                    <Field label={t('address')} error={form.errors.address}><Input value={form.data.address} onChange={event => form.setData('address', event.target.value)} /></Field>
                    <Field label={t('status')} error={form.errors.status}><Select value={form.data.status} onChange={event => form.setData('status', event.target.value)}><option value="active">{t('active')}</option><option value="maintenance">{t('maintenance')}</option><option value="closed">{t('closed')}</option></Select></Field>
                    <Field label={t('pricePerHour')} error={form.errors.price_per_hour}><Input type="number" min="0" step="0.01" value={form.data.price_per_hour} onChange={event => form.setData('price_per_hour', Number(event.target.value))} /></Field>
                </div>

                <h3>{t('weeklyHours')}</h3>
                <div className="schedule-grid">
                    {form.data.operating_hours.map((hour, index) => <div className="schedule-row" key={hour.day_of_week}>
                        <strong>{t(`day${hour.day_of_week}` as any)}</strong>
                        <label><input type="checkbox" checked={hour.is_closed} onChange={event => updateHour(index, { is_closed: event.target.checked })} /> {t('closed')}</label>
                        <Input aria-label={`${dayNames[index]} ${t('openingTime')}`} type="time" disabled={hour.is_closed} value={hour.opening_time} onChange={event => updateHour(index, { opening_time: event.target.value })} />
                        <Input aria-label={`${dayNames[index]} ${t('closingTime')}`} type="time" disabled={hour.is_closed} value={hour.closing_time} onChange={event => updateHour(index, { closing_time: event.target.value })} />
                    </div>)}
                </div>

                <div className="form-actions">
                    <Button type="button" variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button>
                    <Button disabled={form.processing}>{t('save')}</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>;
}
