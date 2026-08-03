import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Pencil, Phone, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Input, PageHeader, Pagination, SearchInput, Select } from '../../Components/UI';
import { localeCode } from '../../lib/dateControls';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

export default function Customers({ customers, filters, fields }: { customers: Paginated<any>; filters: { search?: string }; fields: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const { auth, locale } = usePage<SharedProps>().props;
    const formatterLocale = localeCode(locale);
    const canManageCustomers = auth.user?.role === 'owner' || auth.user?.role === 'employee';
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<any>(null);
    const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
    const profile = useForm({ name: '', phone: '', preferred_field_id: '' as number | string, reliability_status: 'reliable' });
    const note = useForm({ note: '' });
    const editNote = useForm({ note: '' });
    const selectedId = selected?.id;
    const openCustomer = (customer: any) => {
        setSelected(customer);
        setEditingNoteId(null);
        profile.setData({ name: customer.name, phone: customer.phone, preferred_field_id: customer.preferred_field_id ?? '', reliability_status: customer.reliability_status ?? 'reliable' });
    };
    const formatDate = (value?: string | null) => value ? new Intl.DateTimeFormat(formatterLocale, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : t('noData');
    const formatDateTime = (value?: string | null) => value ? new Intl.DateTimeFormat(formatterLocale, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : t('noData');
    const formatTimeRange = (reservation: any) => `${new Intl.DateTimeFormat(formatterLocale, { hour: '2-digit', minute: '2-digit' }).format(new Date(reservation.starts_at))}–${new Intl.DateTimeFormat(formatterLocale, { hour: '2-digit', minute: '2-digit' }).format(new Date(reservation.ends_at))}`;
    const reliabilityLabel = (status?: string | null) => status === 'high_risk' ? t('reliabilityBlocked') : status === 'needs_attention' ? t('reliabilityWatch') : t('reliabilityGood');

    useEffect(() => {
        if (!selectedId) return;
        const fresh = customers.data.find(customer => customer.id === selectedId);
        if (fresh) setSelected(fresh);
    }, [customers.data, selectedId]);

    return <AppLayout title={t('customers')}><Head title={t('customers')} /><div className="owner-page">
        <PageHeader eyebrow={t('relationships')} title={t('customers')} description={t('customersIntro')} />
        <section className="data-toolbar"><form onSubmit={event => { event.preventDefault(); router.get('/customers', { search }, { preserveState: true, replace: true }); }}><SearchInput aria-label={t('search')} placeholder={`${t('searchCustomer')}…`} value={search} onChange={event => setSearch(event.target.value)} /></form></section>
        {customers.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} /></section> : <>
            <div className="table-wrap modern-table"><table><thead><tr><th>{t('customer')}</th><th>{t('reliability')}</th><th>{t('totalBookings')}</th><th>{t('lastBooking')}</th><th>{t('unpaidBookings')}</th><th>{t('noShows')}</th><th>{t('actions')}</th></tr></thead><tbody>{customers.data.map(customer => <tr key={customer.id} onClick={() => openCustomer(customer)} className="clickable-row">
                <td data-label={t('customer')}><div className="identity-cell"><span><UserRound size={17} /></span><div><strong>{customer.name}</strong><small>{customer.phone}</small></div></div></td><td data-label={t('reliability')}><div className="score-cell"><strong>{reliabilityLabel(customer.reliability_status)}</strong><Badge value={customer.reliability_status} /></div></td><td data-label={t('totalBookings')}>{customer.total_reservations}</td><td data-label={t('lastBooking')}>{formatDate(customer.last_visit_at)}</td><td data-label={t('unpaidBookings')}><strong>{customer.unpaid_reservations_count ?? 0}</strong></td><td data-label={t('noShows')}>{customer.no_shows}</td><td data-label={t('actions')}><button className="icon-btn bordered" onClick={event => { event.stopPropagation(); openCustomer(customer); }} aria-label={t('profile')}><ChevronRight size={18} /></button></td>
            </tr>)}</tbody></table></div>{customers.last_page > 1 && <Pagination links={customers.links} />}</>}

        <Drawer open={Boolean(selected)} title={selected?.name ?? ''} subtitle={selected?.phone} onClose={() => setSelected(null)} footer={<><Button variant="secondary" onClick={() => setSelected(null)}>{t('close')}</Button>{canManageCustomers && <Button disabled={profile.processing} onClick={() => profile.put(`/customers/${selected.id}`)}>{t('saveProfile')}</Button>}</>}>
            {selected && <div className="drawer-sections">
                <section className="drawer-summary"><div><span>{t('reliability')}</span><strong>{reliabilityLabel(selected.reliability_status)}</strong><Badge value={selected.reliability_status} /></div><div><span>{t('totalBookings')}</span><strong>{selected.total_reservations}</strong></div><div><span>{t('lastBooking')}</span><strong>{formatDate(selected.last_visit_at)}</strong></div><div><span>{t('unpaidBookings')}</span><strong>{selected.unpaid_reservations_count ?? 0}</strong></div><div><span>{t('noShows')}</span><strong>{selected.no_shows}</strong></div></section>
                {selected.reliability_status === 'high_risk' && <p className="form-callout danger">{t('customerBlockedWarning')}</p>}
                {selected.no_shows >= 2 && selected.reliability_status === 'reliable' && <p className="form-callout warning">{t('customerWatchSuggestion')}</p>}
                <section><h3>{t('customerInformation')}</h3>{canManageCustomers ? <div className="form-grid one-column"><Field label={t('name')} error={profile.errors.name}><Input value={profile.data.name} onChange={event => profile.setData('name', event.target.value)} /></Field><Field label={t('phone')} error={profile.errors.phone}><Input value={profile.data.phone} onChange={event => profile.setData('phone', event.target.value)} /></Field><Field label={t('reliability')} error={profile.errors.reliability_status}><Select value={profile.data.reliability_status} onChange={event => profile.setData('reliability_status', event.target.value)}><option value="reliable">{t('reliabilityGood')}</option><option value="needs_attention">{t('reliabilityWatch')}</option><option value="high_risk">{t('reliabilityBlocked')}</option></Select></Field><Field label={t('preferredField')}><Select value={profile.data.preferred_field_id} onChange={event => profile.setData('preferred_field_id', event.target.value)}><option value="">{t('none')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field></div> : <dl className="detail-list"><div><dt>{t('name')}</dt><dd>{selected.name}</dd></div><div><dt>{t('phone')}</dt><dd>{selected.phone}</dd></div><div><dt>{t('reliability')}</dt><dd>{reliabilityLabel(selected.reliability_status)}</dd></div><div><dt>{t('preferredField')}</dt><dd>{selected.preferred_field?.name ?? t('none')}</dd></div></dl>}</section>
                <section><h3>{t('reservationHistory')}</h3>{selected.reservations.length === 0 ? <p className="drawer-muted">{t('noResults')}</p> : <div className="drawer-list">{selected.reservations.map((reservation: any) => <article key={reservation.id}><div><strong>{reservation.football_field.name}</strong><span>{formatDateTime(reservation.starts_at)} · {formatTimeRange(reservation)}</span>{reservation.notes && <small>{t('bookingPrivateNote')}: {reservation.notes}</small>}</div><div><Badge value={reservation.payment_status} /><Badge value={reservation.status} /></div></article>)}</div>}</section>
                <section><h3>{t('generalCustomerNotes')}</h3>{canManageCustomers && <form className="drawer-note-form" onSubmit={event => { event.preventDefault(); note.post(`/customers/${selected.id}/notes`, { preserveScroll: true, onSuccess: () => note.reset() }); }}><Field label={t('addNote')} error={note.errors.note}><textarea className="input" value={note.data.note} onChange={event => note.setData('note', event.target.value)} /></Field><Button disabled={note.processing}>{t('addNote')}</Button></form>}<div className="drawer-list notes-list">{selected.notes.map((item: any) => <article key={item.id} className={editingNoteId === item.id ? 'editing' : ''}>
                    {editingNoteId === item.id ? <form className="note-edit-form" onSubmit={event => {
                        event.preventDefault();
                        editNote.put(`/customers/${selected.id}/notes/${item.id}`, {
                            preserveScroll: true,
                            onSuccess: () => setEditingNoteId(null),
                        });
                    }}>
                        <Field label={t('editNote')} error={editNote.errors.note}><textarea className="input" value={editNote.data.note} onChange={event => editNote.setData('note', event.target.value)} /></Field>
                        <div className="note-edit-actions">
                            <Button disabled={editNote.processing}><Check size={15} />{t('save')}</Button>
                            <Button type="button" variant="secondary" onClick={() => { setEditingNoteId(null); editNote.clearErrors(); }}><X size={15} />{t('cancel')}</Button>
                        </div>
                    </form> : <>
                        <div><strong>{item.note}</strong><span>{item.user?.name ?? t('system')} · {formatDate(item.created_at)}</span></div>
                        {canManageCustomers && item.user_id === auth.user?.id && <button className="icon-btn bordered" type="button" title={t('editNote')} aria-label={t('editNote')} onClick={() => { setEditingNoteId(item.id); editNote.setData('note', item.note); editNote.clearErrors(); }}><Pencil size={15} /></button>}
                    </>}
                </article>)}</div></section>
                <a className="drawer-contact" href={`tel:${selected.phone}`}><Phone size={17} />{t('callCustomer')}</a>
            </div>}
        </Drawer>
    </div></AppLayout>;
}
