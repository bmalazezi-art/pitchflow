import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Building2, CheckCircle2, CircleDollarSign, Clock3, Eye, EyeOff, MapPin, MoreHorizontal, Phone, Plus, Search, ShieldAlert, ShieldCheck, UserRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Input, Modal, PageHeader, Pagination, Select } from '../../Components/UI';
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
    const [details, setDetails] = useState<OrganizationRow | null>(null);
    const [editing, setEditing] = useState<OrganizationRow | null>(null);
    const [menu, setMenu] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');
    const subscriptionForm = useForm({ plan_name: '1–2 Fields', price: '0', billing_cycle: 'monthly', status: 'active', expires_at: '' });
    const hasFilters = Boolean(filters.search || filters.status || filters.city || filters.subscription || filters.visibility);

    const updateFilters = (next: Partial<Filters>) => router.get('/admin/organizations', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });
    useEffect(() => {
        if (search === (filters.search ?? '')) return;
        const timer = window.setTimeout(() => updateFilters({ search }), 350);
        return () => window.clearTimeout(timer);
    }, [search]);

    const setStatus = (organization: OrganizationRow, status: string) => {
        const destructive = status === 'suspended' || status === 'rejected';
        if (destructive && !window.confirm(status === 'suspended' ? t('suspendOrganizationConfirm') : t('rejectOrganizationConfirm'))) return;
        router.patch(`/admin/organizations/${organization.id}`, { status }, { preserveScroll: true, onSuccess: () => { setMenu(null); setDetails(null); } });
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

    return <AppLayout title={t('organizations')}><Head title={t('organizations')} /><div className="owner-page admin-organizations-page">
        <PageHeader title={t('organizations')} description={t('organizationsIntro')} actions={<Button disabled title={t('manualCreationFuture')}><Plus size={18} />{t('addOrganization')}</Button>} />
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

        {organizations.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={filters.status === 'pending' && !hasFilters ? t('noPendingBusinesses') : t('noBusinessesMatch')} /></section> : <div className="table-wrap modern-table admin-organizations-table"><table><thead><tr><th>{t('business')}</th><th>{t('ownerContact')}</th><th>{t('city')}</th><th>{t('status')}</th><th>{t('subscription')}</th><th>{t('fields')}</th><th>{t('users')}</th><th>{t('reservations')}</th><th>{t('publicVisibility')}</th><th>{t('actions')}</th></tr></thead><tbody>{organizations.data.map(organization => {
            const owner = organization.users?.[0];
            const publicState = visibility(organization);
            const VisibilityIcon = publicState.icon;
            return <tr key={organization.id} className={organization.status === 'pending' ? 'pending-organization' : ''}>
                <td data-label={t('business')}><div className="admin-business-cell"><span><Building2 size={17} /></span><div><strong>{organization.name}{isNew(organization) && <em>{t('newBadge')}</em>}</strong><small>{t('registered')} {formatDate(organization.created_at)}</small></div></div></td>
                <td data-label={t('ownerContact')}><div className="admin-contact-cell"><strong>{owner?.name ?? t('owner')}</strong><a href={`mailto:${owner?.email ?? organization.email}`}>{owner?.email ?? organization.email}</a><a href={`tel:${owner?.phone ?? organization.phone}`}>{owner?.phone ?? organization.phone}</a></div></td>
                <td data-label={t('city')}>{organization.city?.name ?? t('missingData')}</td>
                <td data-label={t('status')}><Badge value={organization.status} /></td>
                <td data-label={t('subscription')}>{organization.latest_subscription ? <div className="admin-subscription-cell"><strong>{organization.latest_subscription.plan_name}</strong><span><Badge value={organization.latest_subscription.status} /> {organization.latest_subscription.price} EUR / {organization.latest_subscription.billing_cycle === 'monthly' ? t('monthShort') : t('yearShort')}</span></div> : <span className="muted-cell">{t('noData')}</span>}</td>
                <td data-label={t('fields')}><strong>{organization.football_fields_count}</strong></td><td data-label={t('users')}><strong>{organization.users_count}</strong></td><td data-label={t('reservations')}><strong>{organization.reservations_count}</strong></td>
                <td data-label={t('publicVisibility')}><span className={`visibility-state visibility-${publicState.value}`}><VisibilityIcon size={14} />{publicState.label}</span></td>
                <td data-label={t('actions')}><div className="admin-row-actions"><button className="compact-action" onClick={() => setDetails(organization)}><Eye size={15} />{t('view')}</button><div className="action-menu-anchor"><button className="icon-btn bordered" onClick={() => setMenu(menu === organization.id ? null : organization.id)} aria-label={t('moreActions')}><MoreHorizontal size={17} /></button>{menu === organization.id && <div className="admin-action-menu">
                    {organization.status === 'pending' && <><button onClick={() => setStatus(organization, 'approved')}><CheckCircle2 size={15} />{t('approve')}</button><button className="danger" onClick={() => setStatus(organization, 'rejected')}><EyeOff size={15} />{t('reject')}</button></>}
                    {organization.status === 'approved' && <button className="danger" onClick={() => setStatus(organization, 'suspended')}><ShieldAlert size={15} />{t('suspend')}</button>}
                    {organization.status === 'suspended' && <button onClick={() => setStatus(organization, 'approved')}><ShieldCheck size={15} />{t('reactivate')}</button>}
                    {organization.status !== 'pending' && <button onClick={() => openSubscription(organization)}><CircleDollarSign size={15} />{t('manageSubscription')}</button>}
                </div>}</div></div></td>
            </tr>;
        })}</tbody></table></div>}
        {organizations.last_page > 1 && <Pagination links={organizations.links} />}

        <Drawer open={Boolean(details)} title={details?.name ?? ''} subtitle={details?.city?.name ?? t('missingData')} onClose={() => setDetails(null)} footer={details && <><Button variant="secondary" onClick={() => setDetails(null)}>{t('close')}</Button>{details.status === 'pending' && <><Button variant="secondary" onClick={() => setStatus(details, 'rejected')}>{t('reject')}</Button><Button variant="success" onClick={() => setStatus(details, 'approved')}>{t('approve')}</Button></>}{details.status === 'approved' && <Button variant="danger" onClick={() => setStatus(details, 'suspended')}>{t('suspend')}</Button>}{details.status === 'suspended' && <Button variant="success" onClick={() => setStatus(details, 'approved')}>{t('reactivate')}</Button>}{details.status !== 'pending' && <Button onClick={() => { setDetails(null); openSubscription(details); }}>{t('manageSubscription')}</Button>}</>}>
            {details && <div className="drawer-sections admin-organization-drawer"><section className="admin-drawer-identity"><span><Building2 size={22} /></span><div><h3>{details.name}</h3><div><Badge value={details.status} />{isNew(details) && <em>{t('newBadge')}</em>}</div></div></section><section><h3>{t('businessInformation')}</h3><div className="admin-detail-list"><div><UserRound size={16} /><span>{t('owner')}</span><strong>{details.users?.[0]?.name ?? t('noData')}</strong></div><div><Phone size={16} /><span>{t('ownerContact')}</span><strong>{details.users?.[0]?.email ?? details.email}<br />{details.users?.[0]?.phone ?? details.phone}</strong></div><div><MapPin size={16} /><span>{t('address')}</span><strong>{details.address || t('missingData')}<br />{details.city?.name}</strong></div><div><Clock3 size={16} /><span>{t('registrationDate')}</span><strong>{formatDate(details.created_at)}</strong></div></div></section>
                <section><h3>{t('businessOverview')}</h3><div className="drawer-summary"><div><span>{t('fields')}</span><strong>{details.football_fields_count}</strong></div><div><span>{t('startingPrice')}</span><strong>{details.football_fields_min_price_per_hour ? `${details.football_fields_min_price_per_hour} EUR` : t('noData')}</strong></div><div><span>{t('users')}</span><strong>{details.users_count}</strong></div><div><span>{t('reservations')}</span><strong>{details.reservations_count}</strong></div></div>{details.amenities?.length ? <div className="tag-list admin-amenities">{details.amenities.map(amenity => <span key={amenity}>{t(amenity as any)}</span>)}</div> : null}</section>
                <section><h3>{t('subscription')}</h3>{details.latest_subscription ? <div className="admin-subscription-summary"><CircleDollarSign size={20} /><div><strong>{details.latest_subscription.plan_name}</strong><span>{details.latest_subscription.price} EUR · {t(details.latest_subscription.status as any)}</span></div></div> : <p className="drawer-muted">{t('noData')}</p>}</section>
                <section><h3>{t('publicVisibility')}</h3>{(() => { const state = visibility(details); const Icon = state.icon; return <span className={`visibility-state visibility-${state.value}`}><Icon size={15} />{state.label}</span>; })()}</section>
                <section><h3>{t('adminNotes')}</h3><p className="drawer-muted">{t('noAdminNotes')}</p></section></div>}
        </Drawer>

        <Modal open={Boolean(editing)} title={t('manageSubscription')} onClose={() => setEditing(null)}><form onSubmit={updateSubscription} className="form-grid"><Field label={t('subscriptionPlan')} error={subscriptionForm.errors.plan_name} required><Select value={subscriptionForm.data.plan_name} onChange={event => subscriptionForm.setData('plan_name', event.target.value)}>{plans.map(plan => <option key={plan} value={plan}>{plan}</option>)}</Select></Field><Field label={t('price')} error={subscriptionForm.errors.price} required><Input type="number" min="0" step="0.01" value={subscriptionForm.data.price} onChange={event => subscriptionForm.setData('price', event.target.value)} /></Field><Field label={t('billingCycle')} error={subscriptionForm.errors.billing_cycle} required><Select value={subscriptionForm.data.billing_cycle} onChange={event => subscriptionForm.setData('billing_cycle', event.target.value)}><option value="monthly">{t('monthly')}</option><option value="yearly">{t('yearly')}</option></Select></Field><Field label={t('status')} error={subscriptionForm.errors.status} required><Select value={subscriptionForm.data.status} onChange={event => subscriptionForm.setData('status', event.target.value)}><option value="active">{t('active')}</option><option value="trial">{t('trial')}</option><option value="expired">{t('expired')}</option><option value="cancelled">{t('cancelled')}</option></Select></Field><Field label={t('expiresAt')} error={subscriptionForm.errors.expires_at}><Input type="date" value={subscriptionForm.data.expires_at} onChange={event => subscriptionForm.setData('expires_at', event.target.value)} /></Field><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>{t('cancel')}</Button><Button disabled={subscriptionForm.processing}>{t('save')}</Button></div></form></Modal>
    </div></AppLayout>;
}
