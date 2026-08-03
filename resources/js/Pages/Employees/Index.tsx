import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CheckCircle2, Copy, KeyRound, MailPlus, Pencil, RefreshCw, ShieldCheck, Trash2, UserRound, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge, Button, Drawer, EmptyState, Field, Input, PageHeader, Pagination, Select } from '../../Components/UI';
import AppLayout from '../../Layouts/AppLayout';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

const permissionKeys = ['create_reservations', 'edit_reservations', 'cancel_reservations', 'view_customers', 'add_customer_notes', 'view_calendar', 'view_assigned_fields'] as const;
const ownerOnlyKeys = ['view_reports', 'organization_settings', 'manage_employees', 'manage_fields'] as const;
const permissionPresets = {
    daily: [...permissionKeys],
    booking: ['create_reservations', 'edit_reservations', 'cancel_reservations', 'view_calendar', 'view_assigned_fields'],
    custom: [],
} as const;

export default function Employees({ employees, fields, stats }: { employees: Paginated<any>; fields: Array<{ id: number; name: string }>; stats: { total: number; active: number; invited: number; disabled: number } }) {
    const t = useTranslation();
    const { flash } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [copied, setCopied] = useState(false);
    const [copyAfterResend, setCopyAfterResend] = useState(false);
    const inviteUrl = flash.invite_url ?? flash.invite_link;
    const resetUrl = flash.reset_url ?? flash.reset_link;
    const actionUrl = inviteUrl ?? resetUrl;
    const isResetLink = Boolean(resetUrl && !inviteUrl);
    const form = useForm({ first_name: '', last_name: '', email: '', phone: '', preferred_language: 'en', field_ids: [] as number[], permissions: [...permissionKeys] as string[] });
    useEffect(() => {
        if (!copyAfterResend || !actionUrl) return;
        navigator.clipboard.writeText(actionUrl).then(() => setCopied(true));
        setCopyAfterResend(false);
    }, [actionUrl, copyAfterResend]);
    const show = (employee?: any) => {
        setEditing(employee ?? null);
        const names = employee?.name?.trim().split(/\s+/) ?? [];
        form.clearErrors();
        form.setData(employee ? { first_name: names.shift() ?? '', last_name: names.join(' '), email: employee.email, phone: employee.phone ?? '', preferred_language: employee.preferred_language, field_ids: employee.assigned_fields.map((field: any) => field.id), permissions: employee.permissions ?? [...permissionKeys] } : { first_name: '', last_name: '', email: '', phone: '', preferred_language: 'en', field_ids: [], permissions: [...permissionKeys] });
        setOpen(true);
    };
    const submit = () => editing ? form.put(`/employees/${editing.id}`, { onSuccess: () => setOpen(false) }) : form.post('/employees', { onSuccess: () => setOpen(false) });
    const toggle = (key: 'field_ids' | 'permissions', value: number | string) => form.setData(key, (form.data[key] as any[]).includes(value) ? (form.data[key] as any[]).filter(item => item !== value) : [...(form.data[key] as any[]), value] as any);
    const applyPermissionPreset = (preset: keyof typeof permissionPresets) => {
        if (preset === 'custom') return;
        form.setData('permissions', [...permissionPresets[preset]]);
    };
    const copyInviteLink = async () => {
        if (!actionUrl) return;
        await navigator.clipboard.writeText(actionUrl);
        setCopied(true);
    };
    const resendInvitation = (employee: any, copy = false) => {
        setCopyAfterResend(copy);
        setCopied(false);
        router.post(`/employees/${employee.id}/resend-invitation`, {}, { onSuccess: () => setOpen(false) });
    };
    const createResetLink = (employee: any, copy = false) => {
        setCopyAfterResend(copy);
        setCopied(false);
        router.post(`/employees/${employee.id}/reset-password-link`, {}, { onSuccess: () => setOpen(false) });
    };
    return <AppLayout title={t('employees')}><Head title={t('employees')} /><div className="owner-page">
        <PageHeader eyebrow={t('team')} title={t('employees')} description={t('employeeManagementIntro')} actions={<Button onClick={() => show()}><MailPlus size={18} />{t('inviteEmployee')}</Button>} />
        {actionUrl && <section className="dashboard-panel invite-link-panel">
            <div>
                <strong>{flash.success ?? (isResetLink ? t('employeePasswordResetLinkCreated') : t('employeeInvitationCreatedSuccess'))}</strong>
                {(flash.invite_notice || flash.reset_notice) && <span>{flash.invite_notice ?? flash.reset_notice}</span>}
                <p>{actionUrl}</p>
                {copied && <small>{isResetLink ? t('resetLinkCopied') : t('inviteLinkCopied')}</small>}
            </div>
            <Button type="button" variant="secondary" onClick={copyInviteLink}><Copy size={16} />{isResetLink ? t('copyResetLink') : t('copyInviteLink')}</Button>
        </section>}
        <section className="owner-summary-grid employee-status-grid">
            <article><span><Users size={18} /></span><div><small>{t('teamMembers')}</small><strong>{stats.total}</strong></div></article>
            <article><span className="success"><CheckCircle2 size={18} /></span><div><small>{t('active')}</small><strong>{stats.active}</strong></div></article>
            <article><span className="warning"><MailPlus size={18} /></span><div><small>{t('invited')}</small><strong>{stats.invited}</strong></div></article>
            <article><span className="danger"><Ban size={18} /></span><div><small>{t('disabled')}</small><strong>{stats.disabled}</strong></div></article>
        </section>
        <div className="employee-summary"><div><ShieldCheck size={19} /><span><strong>{employees.total}</strong>{t('teamMembers')}</span></div><p>{t('employeeAccessSummary')}</p></div>
        {employees.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noEmployees')} action={<Button onClick={() => show()}><MailPlus size={17} />{t('inviteEmployee')}</Button>} /></section> : <>
            <div className="table-wrap modern-table employee-table"><table><thead><tr><th>{t('employee')}</th><th>{t('phone')}</th><th>{t('assignedFields')}</th><th>{t('status')}</th><th>{t('lastLogin')}</th><th>{t('actions')}</th></tr></thead><tbody>{employees.data.map(employee => <tr key={employee.id} onClick={() => show(employee)} className="clickable-row">
                <td data-label={t('employee')}><div className="identity-cell"><span><UserRound size={17} /></span><div><strong>{employee.name}</strong><small>{employee.email || employee.phone}</small></div></div></td><td data-label={t('phone')}>{employee.phone || t('none')}</td><td data-label={t('assignedFields')}><div className="tag-list">{employee.assigned_fields.length ? employee.assigned_fields.map((field: any) => <span key={field.id}>{field.name}</span>) : t('none')}</div></td><td data-label={t('status')}><Badge value={employee.status ?? 'active'} /></td><td data-label={t('lastLogin')}>{employee.last_login_at ? new Date(employee.last_login_at).toLocaleString() : t('never')}</td><td data-label={t('actions')}><div className="row-action-group"><button className="icon-btn bordered" onClick={event => { event.stopPropagation(); show(employee); }} title={t('edit')}><Pencil size={17} /></button>{['active', 'invited'].includes(employee.status) && <button className="icon-btn bordered" onClick={event => { event.stopPropagation(); createResetLink(employee, true); }} title={t('resetPassword')} aria-label={t('resetPassword')}><KeyRound size={17} /></button>}</div></td>
            </tr>)}</tbody></table></div>{employees.last_page > 1 && <Pagination links={employees.links} />}</>}
        <Drawer open={open} title={editing ? editing.name : t('inviteEmployee')} subtitle={editing?.email ?? editing?.phone ?? t('secureInvitationHelp')} onClose={() => setOpen(false)} footer={<><Button variant="secondary" onClick={() => setOpen(false)}>{t('cancel')}</Button>{editing?.status === 'invited' && <><Button variant="secondary" onClick={() => resendInvitation(editing)}><RefreshCw size={16} />{t('resendInvitation')}</Button><Button variant="secondary" onClick={() => resendInvitation(editing, true)}><Copy size={16} />{t('copyInviteLink')}</Button></>}{editing && ['active', 'invited'].includes(editing.status) && <Button variant="secondary" onClick={() => createResetLink(editing, true)}><KeyRound size={16} />{t('resetPassword')}</Button>}{editing && <Button variant="secondary" onClick={() => router.patch(`/employees/${editing.id}/status`, {}, { onSuccess: () => setOpen(false) })}>{editing.status === 'disabled' ? <CheckCircle2 size={16} /> : <Ban size={16} />}{editing.status === 'disabled' ? t('enable') : t('disable')}</Button>}<Button disabled={form.processing} onClick={submit}>{editing ? t('saveChanges') : t('sendInvitation')}</Button></>}>
            <div className="drawer-sections employee-drawer"><section><h3>{t('employeeInformation')}</h3><div className="form-grid"><Field label={t('firstName')} error={form.errors.first_name} required><Input value={form.data.first_name} onChange={e => form.setData('first_name', e.target.value)} /></Field><Field label={t('lastName')} error={form.errors.last_name} required><Input value={form.data.last_name} onChange={e => form.setData('last_name', e.target.value)} /></Field><Field label={t('emailOptional')} error={form.errors.email}><Input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} /></Field><Field label={t('phone')} error={form.errors.phone} required><Input inputMode="tel" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} /></Field><Field label={t('language')}><Select value={form.data.preferred_language} onChange={e => form.setData('preferred_language', e.target.value)}><option value="en">English</option><option value="sq">Shqip</option></Select></Field></div>{!editing && <div className="invitation-callout"><KeyRound size={18} /><p>{t('invitationHint')}</p></div>}</section>
                <section><h3>{t('assignedFields')}</h3><p className="section-help">{t('assignedFieldsPermissionHelp')}</p><div className="assignment-grid">{fields.map(field => <label key={field.id} className={form.data.field_ids.includes(field.id) ? 'selected' : ''}><input type="checkbox" checked={form.data.field_ids.includes(field.id)} onChange={() => toggle('field_ids', field.id)} /><span>{field.name}</span></label>)}</div>{form.errors.field_ids && <small className="field-error">{form.errors.field_ids}</small>}</section>
                <section><h3>{t('operationalPermissions')}</h3><p className="section-help">{t('operationalPermissionsHelp')}</p><div className="permission-preset-row"><Button type="button" variant="secondary" onClick={() => applyPermissionPreset('daily')}>{t('dailyOperationsPreset')}</Button><Button type="button" variant="secondary" onClick={() => applyPermissionPreset('booking')}>{t('bookingOnlyPreset')}</Button><Button type="button" variant="secondary" onClick={() => applyPermissionPreset('custom')}>{t('customPermissions')}</Button></div><div className="permission-list">{permissionKeys.map(key => <label key={key}><input type="checkbox" checked={form.data.permissions.includes(key)} onChange={() => toggle('permissions', key)} /><span><strong>{t(key as any)}</strong><small>{t(`${key}_help` as any)}</small></span></label>)}</div></section>
                <section className="owner-only-permissions"><h3>{t('ownerOnlyAccess')}</h3><p className="section-help">{t('ownerOnlyAccessHelp')}</p><div className="permission-list">{ownerOnlyKeys.map(key => <label key={key} className="locked"><input type="checkbox" disabled /><span><strong>{t(key as any)}</strong></span><ShieldCheck size={16} /></label>)}</div></section>
                {editing && <section className="danger-zone"><h3>{t('dangerZone')}</h3><p>{t('removeEmployeeHelp')}</p><Button variant="danger" onClick={() => confirm(t('removeEmployeeConfirm')) && router.delete(`/employees/${editing.id}`, { onSuccess: () => setOpen(false) })}><Trash2 size={16} />{t('delete')}</Button></section>}
            </div>
        </Drawer>
    </div></AppLayout>;
}
