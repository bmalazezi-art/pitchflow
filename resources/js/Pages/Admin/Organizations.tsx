import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Field, Input, Modal, Pagination, Select } from '../../Components/UI';
import type { Paginated } from '../../types';
import { useTranslation } from '../../lib/i18n';

interface OrganizationRow {
    id: number;
    name: string;
    email: string;
    phone: string;
    status: string;
    city?: { id: number; name: string } | null;
    football_fields_count: number;
    users_count: number;
    reservations_count: number;
    latest_subscription?: {
        id: number;
        plan_name: string;
        price: string;
        billing_cycle: string;
        status: string;
        expires_at?: string | null;
    } | null;
}

export default function Organizations({ organizations, summary }: { organizations: Paginated<OrganizationRow>; summary: Record<string, number> }) {
    const t = useTranslation();
    const [editing, setEditing] = useState<OrganizationRow | null>(null);
    const subscriptionForm = useForm({
        plan_name: '1–2 Fields',
        price: '0',
        billing_cycle: 'monthly',
        status: 'active',
        expires_at: '',
    });
    const setStatus = (id: number, status: string) => router.patch(`/admin/organizations/${id}`, { status });

    const openSubscription = (organization: OrganizationRow) => {
        setEditing(organization);
        subscriptionForm.setData({
            plan_name: organization.latest_subscription?.plan_name ?? '1–2 Fields',
            price: organization.latest_subscription?.price ?? '0',
            billing_cycle: organization.latest_subscription?.billing_cycle ?? 'monthly',
            status: organization.latest_subscription?.status ?? 'active',
            expires_at: organization.latest_subscription?.expires_at?.slice(0, 10) ?? '',
        });
        subscriptionForm.clearErrors();
    };

    const updateSubscription = (event: FormEvent) => {
        event.preventDefault();
        if (!editing) return;
        subscriptionForm.put(`/admin/organizations/${editing.id}/subscription`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return <AppLayout title={t('organizations')}>
        <Head title={t('organizations')} />
        <div className="page-header"><div><h1>{t('organizations')}</h1><p>{t('organizationsIntro')}</p></div></div>
        <section className="metrics-grid">{['pending', 'approved', 'suspended', 'rejected'].map(status => <div className="metric" key={status}><span>{t(status as any)}</span><strong>{summary[status] ?? 0}</strong></div>)}</section>
        <div className="table-wrap">
            <table>
                <thead><tr><th>{t('business')}</th><th>{t('city')}</th><th>{t('status')}</th><th>{t('subscription')}</th><th>{t('fields')}</th><th>{t('users')}</th><th>{t('reservations')}</th><th>{t('actions')}</th></tr></thead>
                <tbody>{organizations.data.map(org => <tr key={org.id}>
                    <td><strong>{org.name}</strong><br /><small>{org.email} · {org.phone}</small></td>
                    <td>{org.city?.name}</td>
                    <td><Badge value={org.status} /></td>
                    <td>{org.latest_subscription ? <><strong>{org.latest_subscription.plan_name}</strong><br /><small>{org.latest_subscription.price} EUR · <Badge value={org.latest_subscription.status} /></small></> : t('noData')}</td>
                    <td>{org.football_fields_count}</td>
                    <td>{org.users_count}</td>
                    <td>{org.reservations_count}</td>
                    <td><div className="actions">
                        {org.status !== 'approved' && <Button variant="success" onClick={() => setStatus(org.id, 'approved')}>{t('approve')}</Button>}
                        {org.status !== 'suspended' && <Button variant="danger" onClick={() => setStatus(org.id, 'suspended')}>{t('suspend')}</Button>}
                        {org.status === 'pending' && <Button variant="secondary" onClick={() => setStatus(org.id, 'rejected')}>{t('reject')}</Button>}
                        <Button variant="secondary" onClick={() => openSubscription(org)}>{t('manageSubscription')}</Button>
                    </div></td>
                </tr>)}</tbody>
            </table>
        </div>
        <Pagination links={organizations.links} />
        <Modal open={Boolean(editing)} title={t('manageSubscription')} onClose={() => setEditing(null)}>
            <form onSubmit={updateSubscription} className="form-grid">
                <Field label={t('subscriptionPlan')} error={subscriptionForm.errors.plan_name} required>
                    <Select value={subscriptionForm.data.plan_name} onChange={event => subscriptionForm.setData('plan_name', event.target.value)}>
                        <option value="1–2 Fields">1–2 Fields</option>
                        <option value="3–5 Fields">3–5 Fields</option>
                        <option value="6+ Fields">6+ Fields</option>
                    </Select>
                </Field>
                <Field label={t('price')} error={subscriptionForm.errors.price} required>
                    <Input type="number" min="0" step="0.01" value={subscriptionForm.data.price} onChange={event => subscriptionForm.setData('price', event.target.value)} />
                </Field>
                <Field label={t('billingCycle')} error={subscriptionForm.errors.billing_cycle} required>
                    <Select value={subscriptionForm.data.billing_cycle} onChange={event => subscriptionForm.setData('billing_cycle', event.target.value)}>
                        <option value="monthly">{t('monthly')}</option>
                        <option value="yearly">{t('yearly')}</option>
                    </Select>
                </Field>
                <Field label={t('status')} error={subscriptionForm.errors.status} required>
                    <Select value={subscriptionForm.data.status} onChange={event => subscriptionForm.setData('status', event.target.value)}>
                        <option value="active">{t('active')}</option>
                        <option value="trial">{t('trial')}</option>
                        <option value="expired">{t('expired')}</option>
                        <option value="cancelled">{t('cancelled')}</option>
                    </Select>
                </Field>
                <Field label={t('expiresAt')} error={subscriptionForm.errors.expires_at}>
                    <Input type="date" value={subscriptionForm.data.expires_at} onChange={event => subscriptionForm.setData('expires_at', event.target.value)} />
                </Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>{t('cancel')}</Button><Button disabled={subscriptionForm.processing}>{t('save')}</Button></div>
            </form>
        </Modal>
    </AppLayout>;
}
