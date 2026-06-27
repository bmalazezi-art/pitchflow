import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Drawer, EmptyState, Field, Input, PageHeader, Pagination, Select } from '../../Components/UI';
import AppLayout from '../../Layouts/AppLayout';
import { useTranslation } from '../../lib/i18n';
import type { Paginated } from '../../types';

export default function Employees({ employees, fields }: { employees: Paginated<any>; fields: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const form = useForm({ name: '', email: '', phone: '', preferred_language: 'en', field_ids: [] as number[] });
    const show = (employee?: any) => { setEditing(employee ?? null); form.setData(employee ? { name: employee.name, email: employee.email, phone: employee.phone ?? '', preferred_language: employee.preferred_language, field_ids: employee.assigned_fields.map((field: any) => field.id) } : { name: '', email: '', phone: '', preferred_language: 'en', field_ids: [] }); setOpen(true); };
    const submit = () => { if (editing) form.put(`/employees/${editing.id}`, { onSuccess: () => setOpen(false) }); else form.post('/employees', { onSuccess: () => setOpen(false) }); };
    const toggleField = (fieldId: number) => form.setData('field_ids', form.data.field_ids.includes(fieldId) ? form.data.field_ids.filter(id => id !== fieldId) : [...form.data.field_ids, fieldId]);

    return <AppLayout title={t('employees')}><Head title={t('employees')} /><div className="owner-page">
        <PageHeader eyebrow={t('team')} title={t('employees')} description={t('employeesIntro')} actions={<Button onClick={() => show()}><Plus size={18} />{t('newEmployee')}</Button>} />
        {employees.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noEmployees')} action={<Button onClick={() => show()}><Plus size={17} />{t('newEmployee')}</Button>} /></section> : <>
            <div className="table-wrap modern-table"><table><thead><tr><th>{t('employee')}</th><th>{t('phone')}</th><th>{t('email')}</th><th>{t('assignedFields')}</th><th>{t('status')}</th><th>{t('actions')}</th></tr></thead><tbody>{employees.data.map(employee => <tr key={employee.id} onClick={() => show(employee)} className="clickable-row">
                <td data-label={t('employee')}><div className="identity-cell"><span><UserRound size={17} /></span><strong>{employee.name}</strong></div></td><td data-label={t('phone')}>{employee.phone || t('none')}</td><td data-label={t('email')}>{employee.email}</td><td data-label={t('assignedFields')}><div className="tag-list">{employee.assigned_fields.length ? employee.assigned_fields.map((field: any) => <span key={field.id}>{field.name}</span>) : t('none')}</div></td><td data-label={t('status')}><Badge value="active" /></td><td data-label={t('actions')}><button className="icon-btn bordered" onClick={event => { event.stopPropagation(); show(employee); }} title={t('edit')}><Pencil size={17} /></button></td>
            </tr>)}</tbody></table></div>{employees.last_page > 1 && <Pagination links={employees.links} />}</>}
        <Drawer open={open} title={editing ? editing.name : t('newEmployee')} subtitle={editing?.email} onClose={() => setOpen(false)} footer={<><Button variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button>{editing && <Button variant="danger" onClick={() => confirm(t('removeEmployeeConfirm')) && router.delete(`/employees/${editing.id}`, { onSuccess: () => setOpen(false) })}><Trash2 size={16} />{t('deactivate')}</Button>}<Button disabled={form.processing} onClick={submit}>{t('save')}</Button></>}>
            <div className="drawer-sections"><section><h3>{t('employeeInformation')}</h3><div className="form-grid one-column"><Field label={t('name')} error={form.errors.name} required><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></Field><Field label={t('email')} error={form.errors.email} required><Input type="email" value={form.data.email} onChange={event => form.setData('email', event.target.value)} /></Field><Field label={t('phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={event => form.setData('phone', event.target.value)} /></Field><Field label={t('language')}><Select value={form.data.preferred_language} onChange={event => form.setData('preferred_language', event.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field></div>{!editing && <p className="form-hint">{t('invitationHint')}</p>}</section>
                <section><h3>{t('assignedFields')}</h3><div className="assignment-grid">{fields.map(field => <label key={field.id} className={form.data.field_ids.includes(field.id) ? 'selected' : ''}><input type="checkbox" checked={form.data.field_ids.includes(field.id)} onChange={() => toggleField(field.id)} /><span>{field.name}</span></label>)}</div>{form.errors.field_ids && <small className="field-error">{form.errors.field_ids}</small>}</section>
            </div>
        </Drawer>
    </div></AppLayout>;
}
