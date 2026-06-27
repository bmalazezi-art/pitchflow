import { Head, useForm, usePage } from '@inertiajs/react';
import { Badge, Button, EmptyState, Field, Input, Select } from '../../Components/UI';
import AppLayout from '../../Layouts/AppLayout';
import { useTranslation } from '../../lib/i18n';
import type { SharedProps } from '../../types';

export default function CustomerShow({ customer, fields }: { customer: any; fields: Array<{ id: number; name: string }> }) {
    const t = useTranslation();
    const { auth } = usePage<SharedProps>().props;
    const canManageCustomer = auth.user?.role === 'employee';
    const profile = useForm({ name: customer.name, phone: customer.phone, preferred_field_id: customer.preferred_field_id ?? '' });
    const note = useForm({ note: '' });

    return <AppLayout title={customer.name}>
        <Head title={customer.name} />
        <div className="page-header"><div><h1>{customer.name}</h1><p>{customer.phone}</p></div><Badge value={customer.reliability_status} /></div>
        <section className="metrics-grid">
            <div className="metric"><span>{t('reliabilityScore')}</span><strong>{customer.reliability_score}/100</strong></div>
            <div className="metric"><span>{t('total')}</span><strong>{customer.total_reservations}</strong></div>
            <div className="metric"><span>{t('completed')}</span><strong>{customer.completed_reservations}</strong></div>
            <div className="metric"><span>{t('noShows')}</span><strong>{customer.no_shows}</strong></div>
            <div className="metric"><span>{t('lateCancellations')}</span><strong>{customer.late_cancellations}</strong></div>
        </section>

        <div className="content-grid">
            <section className="panel">
                <h2>{t('reservationHistory')}</h2>
                {customer.reservations.length === 0
                    ? <EmptyState title={t('noResults')} />
                    : <div className="table-wrap"><table><thead><tr>
                        <th>{t('field')}</th><th>{t('date')}</th><th>{t('status')}</th><th>{t('payment')}</th>
                    </tr></thead><tbody>{customer.reservations.map((reservation: any) => <tr key={reservation.id}>
                        <td>{reservation.football_field.name}</td><td>{new Date(reservation.starts_at).toLocaleString()}</td>
                        <td><Badge value={reservation.status} /></td><td><Badge value={reservation.payment_status} /></td>
                    </tr>)}</tbody></table></div>}
            </section>

            <div style={{ display: 'grid', gap: 18 }}>
                <section className="panel">
                    <h2>{t('profile')}</h2>
                    {canManageCustomer ? <form onSubmit={event => { event.preventDefault(); profile.put(`/customers/${customer.id}`); }} style={{ display: 'grid', gap: 12 }}>
                        <Field label={t('name')} error={profile.errors.name}><Input value={profile.data.name} onChange={event => profile.setData('name', event.target.value)} /></Field>
                        <Field label={t('phone')} error={profile.errors.phone}><Input value={profile.data.phone} onChange={event => profile.setData('phone', event.target.value)} /></Field>
                        <Field label={t('preferredField')}><Select value={profile.data.preferred_field_id} onChange={event => profile.setData('preferred_field_id', event.target.value)}><option value="">{t('none')}</option>{fields.map(field => <option key={field.id} value={field.id}>{field.name}</option>)}</Select></Field>
                        <Button disabled={profile.processing}>{t('saveProfile')}</Button>
                    </form> : <dl className="detail-list"><div><dt>{t('name')}</dt><dd>{customer.name}</dd></div><div><dt>{t('phone')}</dt><dd>{customer.phone}</dd></div><div><dt>{t('preferredField')}</dt><dd>{customer.preferred_field?.name ?? t('none')}</dd></div></dl>}
                </section>

                <section className="panel">
                    <h2>{t('privateNotes')}</h2>
                    {canManageCustomer && <form onSubmit={event => { event.preventDefault(); note.post(`/customers/${customer.id}/notes`, { onSuccess: () => note.reset() }); }}>
                        <Field label={t('addNote')} error={note.errors.note}><textarea className="input" value={note.data.note} onChange={event => note.setData('note', event.target.value)} /></Field>
                        <div className="form-actions"><Button disabled={note.processing}>{t('addNote')}</Button></div>
                    </form>}
                    {customer.notes.map((customerNote: any) => <div key={customerNote.id} style={{ padding: '12px 0', borderTop: '1px solid var(--border)' }}>
                        <p>{customerNote.note}</p><small>{customerNote.user?.name} · {new Date(customerNote.created_at).toLocaleString()}</small>
                    </div>)}
                </section>
            </div>
        </div>
    </AppLayout>;
}
