import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button, EmptyState, Field, Input, Modal, Pagination, Select } from '../../Components/UI';
import AppLayout from '../../Layouts/AppLayout';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

export default function Employees({ employees, fields }: { employees: Paginated<any>; fields: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        preferred_language: 'en',
        field_ids: [] as number[],
    });

    const show = (employee?: any) => {
        setEditing(employee ?? null);
        form.setData(employee ? {
            name: employee.name,
            email: employee.email,
            phone: employee.phone ?? '',
            preferred_language: employee.preferred_language,
            field_ids: employee.assigned_fields.map((field: any) => field.id),
        } : {
            name: '',
            email: '',
            phone: '',
            preferred_language: 'en',
            field_ids: [],
        });
        setOpen(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (editing) {
            form.put(`/employees/${editing.id}`, { onSuccess: () => setOpen(false) });
        } else {
            form.post('/employees', { onSuccess: () => setOpen(false) });
        }
    };

    return <AppLayout title={t('employees')}>
        <Head title={t('employees')} />
        <div className="page-header">
            <div><h1>{t('employees')}</h1><p>{t('employeesIntro')}</p></div>
            <Button onClick={() => show()}><Plus size={18} />{t('newEmployee')}</Button>
        </div>

        {employees.data.length === 0
            ? <div className="panel"><EmptyState title={t('noEmployees')} action={<Button onClick={() => show()}>{t('newEmployee')}</Button>} /></div>
            : <>
                <div className="table-wrap"><table><thead><tr>
                    <th>{t('name')}</th><th>{t('email')}</th><th>{t('fields')}</th>
                    <th>{t('language')}</th><th>{t('actions')}</th>
                </tr></thead><tbody>{employees.data.map(employee => <tr key={employee.id}>
                    <td><strong>{employee.name}</strong><br /><small>{employee.phone}</small></td>
                    <td>{employee.email}</td>
                    <td>{employee.assigned_fields.map((field: any) => field.name).join(', ') || t('none')}</td>
                    <td>{employee.preferred_language.toUpperCase()}</td>
                    <td><div className="actions">
                        <button className="icon-btn" onClick={() => show(employee)} title={t('edit')}><Pencil size={17} /></button>
                        <button className="icon-btn" onClick={() => confirm(t('removeEmployeeConfirm')) && router.delete(`/employees/${employee.id}`)} title={t('delete')}><Trash2 size={17} /></button>
                    </div></td>
                </tr>)}</tbody></table></div>
                <Pagination links={employees.links} />
            </>}

        <Modal open={open} title={editing ? t('editEmployee') : t('newEmployee')} onClose={() => setOpen(false)}>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <Field label={t('name')} error={form.errors.name} required><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></Field>
                    <Field label={t('email')} error={form.errors.email} required><Input type="email" value={form.data.email} onChange={event => form.setData('email', event.target.value)} /></Field>
                    <Field label={t('phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={event => form.setData('phone', event.target.value)} /></Field>
                    <Field label={t('language')}><Select value={form.data.preferred_language} onChange={event => form.setData('preferred_language', event.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field>
                    <Field label={t('assignedFields')} error={form.errors.field_ids} required>
                        <select className="input" multiple size={Math.min(5, fields.length)} value={form.data.field_ids.map(String)} onChange={event => form.setData('field_ids', Array.from(event.target.selectedOptions).map(option => Number(option.value)))}>
                            {fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}
                        </select>
                    </Field>
                    {!editing && <p className="form-hint">{t('invitationHint')}</p>}
                </div>
                <div className="form-actions">
                    <Button type="button" variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button>
                    <Button disabled={form.processing}>{t('save')}</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>;
}
