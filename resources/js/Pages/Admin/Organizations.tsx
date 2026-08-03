import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Building2, CheckCircle2, CircleDollarSign, Clock3, Copy, Eye, EyeOff, MapPin, MoreHorizontal, Phone, Plus, RotateCcw, Search, ShieldAlert, ShieldCheck, UserRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Input, Modal, PageHeader, Pagination, PriceInput, Select } from '../../Components/UI';
import type { Paginated, SharedProps } from '../../types';
import { useTranslation } from '../../lib/i18n';

interface OrganizationRow {
    id: number;
    name: string;
    email: string;
    phone: string;
    address?: string | null;
    amenities?: string[] | null;
    status: string;
    created_at: string;
    city?: { id: number; name: string } | null;
    users?: Array<{ id: number; name: string; email: string; phone?: string | null }>;
    football_fields_count: number;
    active_football_fields_count: number;
    football_fields_min_price_per_hour?: string | null;
    users_count: number;
    reservations_count: number;
    health_status: string;
    visibility_checklist: { is_public: boolean; warnings: string[]; items: Array<{ key: string; complete: boolean }> };
    admin_notes?: Array<{ id: number; note: string; created_at: string; user?: { id: number; name: string } | null }>;
    status_histories?: Array<{ id: number; previous_status: string; new_status: string; reason?: string | null; created_at: string; user?: { id: number; name: string } | null }>;
    latest_subscription?: {
        id: number;
        plan_name: string;
        price: string;
        billing_cycle: string;
        status: string;
        expires_at?: string | null;
    } | null;
}

interface Filters { search: string; status: string; city: number | string; subscription: string; visibility: string }

export default function Organizations({ organizations, summary, filters, cities, plans }: { organizations: Paginated<OrganizationRow>; summary: Record<string, number>; filters: Filters; cities: Array<{ id: number; name: string }>; plans: string[] }) {
    const t = useTranslation();
    const { locale } = usePage<SharedProps>().props;
    const { flash } = usePage<SharedProps>().props;
    const [details, setDetails] = useState<OrganizationRow | null>(null);
    const [editing, setEditing] = useState<OrganizationRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [menu, setMenu] = useState<number | null>(null);
    const [statusDialog, setStatusDialog] = useState<{ organization: OrganizationRow; status: 'rejected' | 'suspended' } | null>(null);
    const [showUndo, setShowUndo] = useState(Boolean(flash.status_undo));
    const [search, setSearch] = useState(filters.search ?? '');
    const subscriptionForm = useForm({ plan_name: '1–2 Fields', price: '0', billing_cycle: 'monthly', status: 'active', expires_at: '' });
    const createForm = useForm({ business_name: '', owner_name: '', owner_phone: '', owner_email: '', city_id: '', address: '', public_phone: '', number_of_fields: 1, starting_price_per_hour: '', status: 'approved' });
    const noteForm = useForm({ note: '' });
    const statusForm = useForm({ status: '', reason: '' });
    const hasFilters = Boolean(filters.search || filters.status || filters.city || filters.subscription || filters.visibility);

    const updateFilters = useCallback((next: Partial<Filters>) => router.get('/admin/organizations', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true }), [filters]);
    useEffect(() => {
        if (search === (filters.search ?? '')) return;
        const timer = window.setTimeout(() => updateFilters({ search }), 350);
        return () => window.clearTimeout(timer);
    }, [filters.search, search, updateFilters]);
    useEffect(() => {
        if (!flash.status_undo) return;
        setShowUndo(true);
        const timer = window.setTimeout(() => setShowUndo(false), 9000);
        return () => window.clearTimeout(timer);
    }, [flash.status_undo]);

    const setStatus = (organization: OrganizationRow, status: string, reason = '') => {
        router.patch(`/admin/organizations/${organization.id}`, { status, reason }, { preserveScroll: true, onSuccess: () => { setMenu(null); setDetails(null); } });
    };
    const openStatusDialog = (organization: OrganizationRow, status: 'rejected' | 'suspended') => {
        setStatusDialog({ organization, status });
        setMenu(null);
        statusForm.setData({ status, reason: '' });
        statusForm.clearErrors();
    };
    const submitStatusDialog = (event: FormEvent) => {
        event.preventDefault();
        if (!statusDialog) return;
        statusForm.patch(`/admin/organizations/${statusDialog.organization.id}`, { preserveScroll: true, onSuccess: () => { setStatusDialog(null); setDetails(null); } });
    };
    const undoStatusChange = () => {
        if (!flash.status_undo) return;
        router.patch(`/admin/organizations/${flash.status_undo.organization_id}`, { status: flash.status_undo.previous_status, reason: t('undoStatusChange') }, { preserveScroll: true, onSuccess: () => setShowUndo(false) });
    };
    const openSubscription = (organization: OrganizationRow) => {
        setEditing(organization);
        setMenu(null);
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
        subscriptionForm.put(`/admin/organizations/${editing.id}/subscription`, { preserveScroll: true, onSuccess: () => setEditing(null) });
    };
    const dateFormatter = useMemo(() => new Intl.DateTimeFormat(locale === 'sq' ? 'sq-AL' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }), [locale]);
    const formatDate = (value: string) => dateFormatter.format(new Date(value));
    const isNew = (organization: OrganizationRow) => Date.now() - new Date(organization.created_at).getTime() <= 30 * 86400000;
    const visibility = (organization: OrganizationRow) => {
        if (organization.status === 'pending') return { value: 'pending', label: t('pendingApproval'), icon: Clock3 };
        if (organization.status !== 'approved') return { value: 'hidden', label: t('hidden'), icon: EyeOff };
        if (!organization.city || !organization.address || organization.active_football_fields_count === 0) return { value: 'missing', label: t('missingData'), icon: ShieldAlert };
        return { value: 'visible', label: t('visible'), icon: Eye };
    };
    const statusActions = (organization: OrganizationRow) => {
        if (organization.status === 'pending') return <>
            <button onClick={() => setStatus(organization, 'approved')}><CheckCircle2 size={15} />{t('approve')}</button>
            <button className="danger" onClick={() => openStatusDialog(organization, 'rejected')}><EyeOff size={15} />{t('reject')}</button>
        </>;
        if (organization.status === 'rejected') return <>
            <button onClick={() => setStatus(organization, 'pending')}><Clock3 size={15} />{t('moveToPending')}</button>
            <button onClick={() => setStatus(organization, 'approved')}><CheckCircle2 size={15} />{t('approve')}</button>
        </>;
        if (organization.status === 'approved') return <>
            <button className="danger" onClick={() => openStatusDialog(organization, 'suspended')}><ShieldAlert size={15} />{t('suspend')}</button>
            <button className="danger" onClick={() => openStatusDialog(organization, 'rejected')}><EyeOff size={15} />{t('reject')}</button>
        </>;
        return <>
            <button onClick={() => setStatus(organization, 'approved')}><ShieldCheck size={15} />{t('reactivate')}</button>
            <button onClick={() => setStatus(organization, 'pending')}><Clock3 size={15} />{t('moveToPending')}</button>
        </>;
    };
    const drawerStatusActions = (organization: OrganizationRow) => {
        if (organization.status === 'pending') return <><Button variant="secondary" onClick={() => openStatusDialog(organization, 'rejected')}>{t('reject')}</Button><Button variant="success" onClick={() => setStatus(organization, 'approved')}>{t('approve')}</Button></>;
        if (organization.status === 'rejected') return <><Button variant="secondary" onClick={() => setStatus(organization, 'pending')}>{t('moveToPending')}</Button><Button variant="success" onClick={() => setStatus(organization, 'approved')}>{t('approve')}</Button></>;
        if (organization.status === 'approved') return <><Button variant="danger" onClick={() => openStatusDialog(organization, 'suspended')}>{t('suspend')}</Button><Button variant="secondary" onClick={() => openStatusDialog(organization, 'rejected')}>{t('reject')}</Button></>;
        return <><Button variant="success" onClick={() => setStatus(organization, 'approved')}>{t('reactivate')}</Button><Button variant="secondary" onClick={() => setStatus(organization, 'pending')}>{t('moveToPending')}</Button></>;
    };
    const healthLabel = (value: string) => value === 'ready' ? t('ready') : value === 'needs_setup' ? t('needsSetup') : value === 'inactive' ? t('inactive') : t('atRisk');
    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post('/admin/organizations', { preserveScroll: true, onSuccess: () => { setCreating(false); createForm.reset(); } });
    };
    const copyInvite = async () => {
        if (!flash.invite_url) return;
        await navigator.clipboard.writeText(flash.invite_url);
    };
    const addNote = (event: FormEvent) => {
        event.preventDefault();
        if (!details) return;
        noteForm.post(`/admin/organizations/${details.id}/notes`, { preserveScroll: true, onSuccess: () => noteForm.reset() });
    };

    return <AppLayout title={t('organizations')}><Head title={t('organizations')} /><div className="owner-page admin-organizations-page">
        <PageHeader title={t('organizations')} description={t('organizationsIntro')} actions={<Button onClick={() => setCreating(true)}><Plus size={18} />{t('addOrganization')}</Button>} />
        {showUndo && flash.status_undo && <div className="toast success toast-with-action"><span>{flash.status_undo.message}</span><button type="button" onClick={undoStatusChange}><RotateCcw size={14} />{t('undo')}</button></div>}
        {flash.invite_url && <div className="form-callout"><strong>{t('ownerSetupLinkCreated')}</strong><div className="actions"><Button type="button" variant="secondary" onClick={copyInvite}><Copy size={16} />{t('copyInviteLink')}</Button><Input readOnly value={flash.invite_url} /></div></div>}
        <section className="admin-status-grid" aria-label={t('organizationStatusSummary')}>{([
            ['pending', Clock3], ['approved', CheckCircle2], ['suspended', ShieldAlert], ['rejected', EyeOff],
        ] as const).map(([status, Icon]) => <button key={status} className={`admin-status-card status-${status} ${filters.status === status ? 'selected' : ''}`} onClick={() => updateFilters({ status: filters.status === status ? '' : status })}><span><Icon size={18} /></span><div><strong>{summary[status] ?? 0}</strong><small>{t(status)}</small></div></button>)}</section>

        <section className="admin-filter-bar"><label className="admin-business-search"><Search size={17} /><input value={search} onChange={event => setSearch(event.target.value)} placeholder={t('searchBusinesses')} aria-label={t('searchBusinesses')} /></label>
            <Select value={filters.status ?? ''} onChange={event => updateFilters({ status: event.target.value })} aria-label={t('status')}><option value="">{t('allStatuses')}</option>{(['pending', 'approved', 'suspended', 'rejected'] as const).map(status => <option key={status} value={status}>{t(status)}</option>)}</Select>
            <Select value={filters.city ?? ''} onChange={event => updateFilters({ city: event.target.value })} aria-label={t('city')}><option value="">{t('allCities')}</option>{cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}</Select>
            <Select value={filters.subscription ?? ''} onChange={event => updateFilters({ subscription: event.target.value })} aria-label={t('subscription')}><option value="">{t('allPlans')}</option>{plans.map(plan => <option key={plan} value={plan}>{plan}</option>)}</Select>
            <Select value={filters.visibility ?? ''} onChange={event => updateFilters({ visibility: event.target.value })} aria-label={t('publicVisibility')}><option value="">{t('allVisibility')}</option><option value="visible">{t('visible')}</option><option value="hidden">{t('hidden')}</option><option value="pending">{t('pendingApproval')}</option><option value="missing">{t('missingData')}</option></Select>
            {hasFilters && <button className="admin-clear-filters" onClick={() => { setSearch(''); router.get('/admin/organizations'); }}>{t('clearFilters')}</button>}
        </section>

        {organizations.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={filters.status === 'pending' && !hasFilters ? t('noPendingBusinesses') : t('noBusinessesMatch')} /></section> : <div className="table-wrap modern-table admin-organizations-table"><table><thead><tr><th>{t('business')}</th><th>{t('ownerContact')}</th><th>{t('city')}</th><th>{t('status')}</th><th>{t('health')}</th><th>{t('fields')}</th><th>{t('users')}</th><th>{t('reservations')}</th><th>{t('publicVisibility')}</th><th>{t('actions')}</th></tr></thead><tbody>{organizations.data.map(organization => {
            const owner = organization.users?.[0];
            const publicState = visibility(organization);
            const VisibilityIcon = publicState.icon;
            return <tr key={organization.id} className={organization.status === 'pending' ? 'pending-organization' : ''}>
                <td data-label={t('business')}><div className="admin-business-cell"><span><Building2 size={17} /></span><div><strong>{organization.name}{isNew(organization) && <em>{t('newBadge')}</em>}</strong><small>{t('registered')} {formatDate(organization.created_at)}</small></div></div></td>
                <td data-label={t('ownerContact')}><div className="admin-contact-cell"><strong>{owner?.name ?? t('owner')}</strong><a href={`mailto:${owner?.email ?? organization.email}`}>{owner?.email ?? organization.email}</a><a href={`tel:${owner?.phone ?? organization.phone}`}>{owner?.phone ?? organization.phone}</a></div></td>
                <td data-label={t('city')}>{organization.city?.name ?? t('missingData')}</td>
                <td data-label={t('status')}><Badge value={organization.status} /></td>
                <td data-label={t('health')}><span className={`visibility-state visibility-${organization.health_status}`}>{healthLabel(organization.health_status)}</span></td>
                <td data-label={t('fields')}><strong>{organization.football_fields_count}</strong></td><td data-label={t('users')}><strong>{organization.users_count}</strong></td><td data-label={t('reservations')}><strong>{organization.reservations_count}</strong></td>
                <td data-label={t('publicVisibility')}><span className={`visibility-state visibility-${publicState.value}`}><VisibilityIcon size={14} />{publicState.label}</span></td>
                <td data-label={t('actions')}><div className="admin-row-actions"><button className="compact-action" onClick={() => setDetails(organization)}><Eye size={15} />{t('view')}</button><div className="action-menu-anchor"><button className="icon-btn bordered" onClick={() => setMenu(menu === organization.id ? null : organization.id)} aria-label={t('moreActions')}><MoreHorizontal size={17} /></button>{menu === organization.id && <div className="admin-action-menu">
                    {statusActions(organization)}
                    {organization.status !== 'pending' && <button onClick={() => openSubscription(organization)}><CircleDollarSign size={15} />{t('manageSubscription')}</button>}
                </div>}</div></div></td>
            </tr>;
        })}</tbody></table></div>}
        {organizations.last_page > 1 && <Pagination links={organizations.links} />}

        <Drawer open={Boolean(details)} title={details?.name ?? ''} subtitle={details?.city?.name ?? t('missingData')} onClose={() => setDetails(null)} footer={details && <><Button variant="secondary" onClick={() => setDetails(null)}>{t('close')}</Button>{drawerStatusActions(details)}{details.status !== 'pending' && <Button onClick={() => { setDetails(null); openSubscription(details); }}>{t('manageSubscription')}</Button>}</>}>
            {details && <div className="drawer-sections admin-organization-drawer"><section className="admin-drawer-identity"><span><Building2 size={22} /></span><div><h3>{details.name}</h3><div><Badge value={details.status} />{isNew(details) && <em>{t('newBadge')}</em>}</div></div></section><section><h3>{t('businessInformation')}</h3><div className="admin-detail-list"><div><UserRound size={16} /><span>{t('owner')}</span><strong>{details.users?.[0]?.name ?? t('noData')}</strong></div><div><Phone size={16} /><span>{t('ownerContact')}</span><strong>{details.users?.[0]?.email ?? details.email}<br />{details.users?.[0]?.phone ?? details.phone}</strong></div><div><MapPin size={16} /><span>{t('address')}</span><strong>{details.address || t('missingData')}<br />{details.city?.name}</strong></div><div><Clock3 size={16} /><span>{t('registrationDate')}</span><strong>{formatDate(details.created_at)}</strong></div></div></section>
                <section><h3>{t('businessOverview')}</h3><div className="drawer-summary"><div><span>{t('fields')}</span><strong>{details.football_fields_count}</strong></div><div><span>{t('startingPrice')}</span><strong>{details.football_fields_min_price_per_hour ? `${details.football_fields_min_price_per_hour} EUR` : t('noData')}</strong></div><div><span>{t('users')}</span><strong>{details.users_count}</strong></div><div><span>{t('reservations')}</span><strong>{details.reservations_count}</strong></div></div>{details.amenities?.length ? <div className="tag-list admin-amenities">{details.amenities.map(amenity => <span key={amenity}>{t(amenity as any)}</span>)}</div> : null}</section>
                <section><h3>{t('subscription')}</h3>{details.latest_subscription ? <div className="admin-subscription-summary"><CircleDollarSign size={20} /><div><strong>{details.latest_subscription.plan_name}</strong><span>{details.latest_subscription.price} EUR · {t(details.latest_subscription.status as any)}</span></div></div> : <p className="drawer-muted">{t('noData')}</p>}</section>
                <section><h3>{t('publicVisibilityChecklist')}</h3><div className="tag-list admin-amenities">{details.visibility_checklist.items.map(item => <span key={item.key} className={item.complete ? 'success' : 'warning'}>{item.complete ? '✓' : '!'} {t(item.key as any)}</span>)}</div>{details.visibility_checklist.warnings.length > 0 && <p className="drawer-muted">{details.visibility_checklist.warnings.map(key => t(key as any)).join(' · ')}</p>}</section>
                <section><h3>{t('statusHistory')}</h3>{details.status_histories?.length ? <div className="drawer-list status-history-list">{details.status_histories.map(entry => <article key={entry.id}><div><strong>{t(entry.previous_status as any)} → {t(entry.new_status as any)}</strong><span>{entry.user?.name ?? t('system')} · {formatDate(entry.created_at)}</span>{entry.reason && <p>{entry.reason}</p>}</div></article>)}</div> : <p className="drawer-muted">{t('noStatusHistory')}</p>}</section>
                <section><h3>{t('adminNotes')}</h3><form className="drawer-note-form" onSubmit={addNote}><Field label={t('addNote')} error={noteForm.errors.note}><textarea className="input" value={noteForm.data.note} onChange={event => noteForm.setData('note', event.target.value)} /></Field><Button disabled={noteForm.processing}>{t('addNote')}</Button></form>{details.admin_notes?.length ? <div className="drawer-list notes-list">{details.admin_notes.map(note => <article key={note.id}><div><strong>{note.note}</strong><span>{note.user?.name ?? t('system')} · {formatDate(note.created_at)}</span></div></article>)}</div> : <p className="drawer-muted">{t('noAdminNotes')}</p>}</section></div>}
        </Drawer>

        <Modal open={Boolean(statusDialog)} title={statusDialog?.status === 'suspended' ? t('suspendOrganizationTitle') : t('rejectOrganizationTitle')} onClose={() => setStatusDialog(null)}>
            <form onSubmit={submitStatusDialog} className="form-grid">
                <p className="modal-helper">{statusDialog?.status === 'suspended' ? t('suspendOrganizationText') : t('rejectOrganizationText')}</p>
                <Field label={statusDialog?.status === 'suspended' ? t('suspensionReason') : t('rejectionReason')} error={statusForm.errors.reason} required>
                    <textarea className="input" value={statusForm.data.reason} onChange={event => statusForm.setData('reason', event.target.value)} />
                </Field>
                <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setStatusDialog(null)}>{t('cancel')}</Button><Button variant="danger" disabled={statusForm.processing}>{statusDialog?.status === 'suspended' ? t('suspend') : t('reject')}</Button></div>
            </form>
        </Modal>

        <Modal open={creating} title={t('addOrganization')} onClose={() => setCreating(false)}><form onSubmit={submitCreate} className="form-grid">
            <Field label={t('businessName')} error={createForm.errors.business_name} required><Input value={createForm.data.business_name} onChange={event => createForm.setData('business_name', event.target.value)} /></Field>
            <Field label={t('owner')} error={createForm.errors.owner_name} required><Input value={createForm.data.owner_name} onChange={event => createForm.setData('owner_name', event.target.value)} /></Field>
            <Field label={t('ownerPhone')} error={createForm.errors.owner_phone} required><Input value={createForm.data.owner_phone} onChange={event => createForm.setData('owner_phone', event.target.value)} /></Field>
            <Field label={t('emailOptional')} error={createForm.errors.owner_email}><Input type="email" value={createForm.data.owner_email} onChange={event => createForm.setData('owner_email', event.target.value)} /></Field>
            <Field label={t('city')} error={createForm.errors.city_id} required><Select value={createForm.data.city_id} onChange={event => createForm.setData('city_id', event.target.value)}><option value="">{t('select')}</option>{cities.map(city => <option key={city.id} value={city.id}>{city.name}</option>)}</Select></Field>
            <Field label={t('address')} error={createForm.errors.address}><Input value={createForm.data.address} onChange={event => createForm.setData('address', event.target.value)} /></Field>
            <Field label={t('publicPhone')} error={createForm.errors.public_phone} required><Input value={createForm.data.public_phone} onChange={event => createForm.setData('public_phone', event.target.value)} /></Field>
            <Field label={t('numberOfFields')} error={createForm.errors.number_of_fields} required><Input type="number" min="1" value={createForm.data.number_of_fields} onChange={event => createForm.setData('number_of_fields', Number(event.target.value))} /></Field>
            <Field label={t('startingHourlyPrice')} error={createForm.errors.starting_price_per_hour}><PriceInput value={createForm.data.starting_price_per_hour} onChange={event => createForm.setData('starting_price_per_hour', event.target.value)} /></Field>
            <Field label={t('status')} error={createForm.errors.status} required><Select value={createForm.data.status} onChange={event => createForm.setData('status', event.target.value)}><option value="approved">{t('approved')}</option><option value="pending">{t('pending')}</option></Select></Field>
            <div className="form-actions"><Button type="button" variant="secondary" onClick={() => setCreating(false)}>{t('cancel')}</Button><Button disabled={createForm.processing}>{t('save')}</Button></div>
        </form></Modal>

        <Modal open={Boolean(editing)} title={t('manageSubscription')} onClose={() => setEditing(null)}><form onSubmit={updateSubscription} className="form-grid"><Field label={t('subscriptionPlan')} error={subscriptionForm.errors.plan_name} required><Select value={subscriptionForm.data.plan_name} onChange={event => subscriptionForm.setData('plan_name', event.target.value)}>{plans.map(plan => <option key={plan} value={plan}>{plan}</option>)}</Select></Field><Field label={t('price')} error={subscriptionForm.errors.price} required><Input type="number" min="0" step="0.01" value={subscriptionForm.data.price} onChange={event => subscriptionForm.setData('price', event.target.value)} /></Field><Field label={t('billingCycle')} error={subscriptionForm.errors.billing_cycle} required><Select value={subscriptionForm.data.billing_cycle} onChange={event => subscriptionForm.setData('billing_cycle', event.target.value)}><option value="monthly">{t('monthly')}</option><option value="yearly">{t('yearly')}</option></Select></Field><Field label={t('status')} error={subscriptionForm.errors.status} required><Select value={subscriptionForm.data.status} onChange={event => subscriptionForm.setData('status', event.target.value)}><option value="active">{t('active')}</option><option value="trial">{t('trial')}</option><option value="expired">{t('expired')}</option><option value="cancelled">{t('cancelled')}</option></Select></Field><Field label={t('expiresAt')} error={subscriptionForm.errors.expires_at}><Input type="date" value={subscriptionForm.data.expires_at} onChange={event => subscriptionForm.setData('expires_at', event.target.value)} /></Field><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>{t('cancel')}</Button><Button disabled={subscriptionForm.processing}>{t('save')}</Button></div></form></Modal>
    </div></AppLayout>;
}
