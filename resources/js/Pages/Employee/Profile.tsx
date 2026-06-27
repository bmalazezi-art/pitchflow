import { Head, useForm } from '@inertiajs/react';
import { Building2, Mail, Phone, UserRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Button, Field, Input, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function EmployeeProfile({ employee }: { employee: any }) {
    const t = useTranslation();
    const form = useForm({ name: employee.name, phone: employee.phone ?? '', preferred_language: employee.preferred_language });

    return <AppLayout title={t('myProfile')}><Head title={t('myProfile')} /><div className="operations-page profile-page">
        <header className="operations-welcome"><div><span>{t('employeeWorkspace')}</span><h1>{t('myProfile')}</h1><p>{t('profileIntro')}</p></div></header>
        <div className="employee-profile-grid"><section className="operations-panel employee-profile-card"><span className="profile-avatar"><UserRound size={28} /></span><h2>{employee.name}</h2><p>{t('employee')}</p><dl><div><dt><Mail size={15} />{t('email')}</dt><dd>{employee.email}</dd></div><div><dt><Phone size={15} />{t('phone')}</dt><dd>{employee.phone || t('none')}</dd></div><div><dt><Building2 size={15} />{t('myAssignedFields')}</dt><dd>{employee.assigned_fields.map((field: any) => field.name).join(', ') || t('none')}</dd></div></dl></section>
            <section className="operations-panel"><header><div><span>{t('account')}</span><h2>{t('personalDetails')}</h2></div></header><form onSubmit={event => { event.preventDefault(); form.put('/profile'); }} className="employee-profile-form"><Field label={t('name')} error={form.errors.name}><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></Field><Field label={t('phone')} error={form.errors.phone}><Input value={form.data.phone} onChange={event => form.setData('phone', event.target.value)} /></Field><Field label={t('language')} error={form.errors.preferred_language}><Select value={form.data.preferred_language} onChange={event => form.setData('preferred_language', event.target.value)}><option value="sq">Shqip</option><option value="en">English</option></Select></Field><div className="form-actions"><Button disabled={form.processing}>{t('saveChanges')}</Button></div></form></section></div>
    </div></AppLayout>;
}
