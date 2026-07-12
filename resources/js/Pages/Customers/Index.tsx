import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Pencil, Phone, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Drawer, EmptyState, Field, Input, PageHeader, Pagination, SearchInput, Select } from '../../Components/UI';
import { useTranslation } from '../../lib/i18n';
import type { Paginated, SharedProps } from '../../types';

export default function Customers({ customers, filters, fields }: { customers: Paginated<any>; filters: { search?: string }; fields: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const canManageCustomers = auth.user?.role === 'employee';
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<any>(null);
    const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
    const profile = useForm({ name: '', phone: '', preferred_field_id: '' as number | string });
    const note = useForm({ note: '' });
    const editNote = useForm({ note: '' });
    const openCustomer = (customer: any) => {
        setSelected(customer);
        setEditingNoteId(null);
        profile.setData({ name: customer.name, phone: customer.phone, preferred_field_id: customer.preferred_field_id ?? '' });
    };
    const formatDate = (value?: string | null) => value ? new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : t('noData');

    useEffect(() => {
        if (!selected) return;
        const fresh = customers.data.find(customer => customer.id === selected.id);
        if (fresh) setSelected(fresh);
    }, [customers.data, selected?.id]);

    return <AppLayout title={t('customers')}><Head title={t('customers')} /><div className="owner-page">
        <PageHeader eyebrow={t('relationships')} title={t('customers')} description={t('customersIntro')} />
        <section className="data-toolbar"><form onSubmit={event => { event.preventDefault(); router.get('/customers', { search }, { preserveState: true, replace: true }); }}><SearchInput aria-label={t('search')} placeholder={`${t('searchCustomer')}…`} value={search} onChange={event => setSearch(event.target.value)} /></form></section>
        {customers.data.length === 0 ? <section className="dashboard-panel"><EmptyState title={t('noResults')} /></section> : <>
            <div className="table-wrap modern-table"><table><thead><tr><th>{t('customer')}</th><th>{t('phone')}</th><th>{t('reliabilityScore')}</th><th>{t('reservations')}</th><th>{t('lastVisit')}</th><th>{t('outstandingBalance')}</th><th>{t('actions')}</th></tr></thead><tbody>{customers.data.map(customer => <tr key={customer.id} onClick={() => openCustomer(customer)} className="clickable-row">
                <td data-label={t('customer')}><div className="identity-cell"><span><UserRound size={17} /></span><strong>{customer.name}</strong></div></td><td data-label={t('phone')}><a href={`tel:${customer.phone}`} onClick={event => event.stopPropagation()}>{customer.phone}</a></td><td data-label={t('reliabilityScore')}><div className="score-cell"><strong>{customer.reliability_score}/100</strong><Badge value={customer.reliability_status} /></div></td><td data-label={t('reservations')}>{customer.total_reservations}</td><td data-label={t('lastVisit')}>{formatDate(customer.last_visit_at)}</td><td data-label={t('outstandingBalance')}><strong>€{Number(customer.outstanding_balance).toFixed(2)}</strong></td><td data-label={t('actions')}><button className="icon-btn bordered" onClick={event => { event.stopPropagation(); openCustomer(customer); }} aria-label={t('profile')}><ChevronRight size={18} /></button></td>
            </tr>)}</tbody></table></div>{customers.last_page > 1 && <Pagination links={customers.links} />}</>}

        <Drawer open={Boolean(selected)} title={selected?.name ?? ''} subtitle={selected?.phone} onClose={() => setSelected(null)} footer={<><Button variant="secondary" onClick={() => setSelected(null)}>{t('close')}</Button>{canManageCustomers && <Button disabled={profile.processing} onClick={() => profile.put(`/customers/${selected.id}`)}>{t('saveProfile')}</Button>}</>}>
            {selected && <div className="drawer-sections">
                <section className="drawer-summary"><div><span>{t('reliabilityScore')}</span><strong>{selected.reliability_score}/100</strong><Badge value={selected.reliability_status} /></div><div><span>{t('reservations')}</span><strong>{selected.total_reservations}</strong></div><div><span>{t('noShows')}</span><strong>{selected.no_shows}</strong></div><div><span>{t('outstandingBalance')}</span><strong>€{Number(selected.outstanding_balance).toFixed(2)}</strong></div></section>
                <section><h3>{t('customerInformation')}</h3>{canManageCustomers ? <div className="form-grid one-column"><Field label={t('name')} error={profile.errors.name}><Input value={profile.data.name} onChange={event => profile.setData('name', event.target.value)} /></Field><Field label={t('phone')} error={profile.errors.phone}><Input value={profile.data.phone} onChange={event => profile.setData('phone', event.target.value)} /></Field><Field label={t('preferredField')}><Select value={profile.data.preferred_field_id} onChange={event => profile.setData('preferred_field_id', event.target.value)}><option value="">{t('none')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field></div> : <dl className="detail-list"><div><dt>{t('name')}</dt><dd>{selected.name}</dd></div><div><dt>{t('phone')}</dt><dd>{selected.phone}</dd></div><div><dt>{t('preferredField')}</dt><dd>{selected.preferred_field?.name ?? t('none')}</dd></div></dl>}</section>
                <section><h3>{t('reservationHistory')}</h3>{selected.reservations.length === 0 ? <p className="drawer-muted">{t('noResults')}</p> : <div className="drawer-list">{selected.reservations.map((reservation: any) => <article key={reservation.id}><div><strong>{reservation.football_field.name}</strong><span>{formatDate(reservation.starts_at)}</span></div><div><Badge value={reservation.payment_status} /><Badge value={reservation.status} /></div></article>)}</div>}</section>
                <section><h3>{t('privateNotes')}</h3>{canManageCustomers && <form className="drawer-note-form" onSubmit={event => { event.preventDefault(); note.post(`/customers/${selected.id}/notes`, { preserveScroll: true, onSuccess: () => note.reset() }); }}><Field label={t('addNote')} error={note.errors.note}><textarea className="input" value={note.data.note} onChange={event => note.setData('note', event.target.value)} /></Field><Button disabled={note.processing}>{t('addNote')}</Button></form>}<div className="drawer-list notes-list">{selected.notes.map((item: any) => <article key={item.id} className={editingNoteId === item.id ? 'editing' : ''}>
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
