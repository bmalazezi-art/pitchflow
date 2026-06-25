import { Head, router, usePage } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Badge } from '../../Components/UI';
import type { SharedProps } from '../../types';

export default function ApprovalPending() {
    const { auth } = usePage<SharedProps>().props;
    const status = auth.organization?.status ?? 'pending';
    return <AuthLayout><Head title="Application status" /><Badge value={status} /><h1 style={{ marginTop: 16 }}>Your organization is {status.replace('_', ' ')}</h1>
        <p>Platform administrators review every business to protect the quality and security of the network.</p>
        {status === 'approved' && <Button onClick={() => router.visit('/dashboard')}>Open dashboard</Button>}
        <Button variant="secondary" onClick={() => router.post('/logout')}>Log out</Button>
    </AuthLayout>;
}
