import { Head, router } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button } from '../../Components/UI';

export default function VerifyEmail() {
    return <AuthLayout><Head title="Verify email" /><h1>Verify your email</h1><p>Open the verification link sent to your inbox before accessing the workspace.</p>
        <Button onClick={() => router.post('/email/verification-notification')}>Resend verification email</Button>
        <Button variant="secondary" onClick={() => router.post('/logout')}>Log out</Button>
    </AuthLayout>;
}
