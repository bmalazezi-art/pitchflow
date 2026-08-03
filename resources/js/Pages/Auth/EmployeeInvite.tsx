import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';

export default function EmployeeInvite({ token, employee }: {
    token: string;
    employee: { name: string; email?: string | null; phone?: string | null; organization?: string | null };
}) {
    const t = useTranslation();
    const form = useForm({ password: '', password_confirmation: '' });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(`/employee/invite/${token}`);
    };

    return <AuthLayout><Head title={t('setEmployeePassword')} />
        <h1>{t('setEmployeePassword')}</h1>
        <p>{t('employeeInviteIntro')} <strong>{employee.organization}</strong>.</p>
        <div className="auth-callout"><strong>{employee.name}</strong><p>{employee.email || employee.phone || t('phoneLoginAvailable')}</p></div>
        <form onSubmit={submit}>
            <Field label={t('password')} error={form.errors.password} required>
                <Input type="password" value={form.data.password} onChange={event => form.setData('password', event.target.value)} autoFocus />
            </Field>
            <Field label={t('confirmPassword')} error={form.errors.password_confirmation} required>
                <Input type="password" value={form.data.password_confirmation} onChange={event => form.setData('password_confirmation', event.target.value)} />
            </Field>
            <Button className="auth-submit" disabled={form.processing}>{t('activateAccount')}</Button>
        </form>
    </AuthLayout>;
}
