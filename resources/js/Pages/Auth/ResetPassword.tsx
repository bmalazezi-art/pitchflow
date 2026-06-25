import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ email, token, password: '', password_confirmation: '' });
    return <AuthLayout><Head title="Choose a new password" /><h1>Choose a new password</h1><p>Use a strong, unique password for this account.</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/reset-password'); }}>
            <Field label="Email" error={form.errors.email}><Input type="email" value={form.data.email} readOnly /></Field>
            <Field label="New password" error={form.errors.password}><Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <Field label="Confirm password"><Input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} /></Field>
            <Button disabled={form.processing}>Reset password</Button>
        </form>
    </AuthLayout>;
}
