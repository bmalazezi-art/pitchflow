import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, Input } from '../../Components/UI';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    return <AuthLayout><Head title="Reset password" /><h1>Reset password</h1><p>We will email a secure reset link if the account exists.</p>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }}>
            <Field label="Email" error={form.errors.email} required><Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Field>
            <Button disabled={form.processing}>Send reset link</Button><Link href="/login">Back to login</Link>
        </form>
    </AuthLayout>;
}
