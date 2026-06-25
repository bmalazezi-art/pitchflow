import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Pagination } from '../../Components/UI';
import type { Paginated } from '../../types';

export default function Organizations({ organizations, summary }: { organizations: Paginated<any>; summary: Record<string, number> }) {
    const setStatus = (id: number, status: string) => router.patch(`/admin/organizations/${id}`, { status });
    return <AppLayout title="Organizations"><Head title="Organizations" /><div className="page-header"><div><h1>Organizations</h1><p>Approve verified businesses and control platform access.</p></div></div>
        <section className="metrics-grid">{['pending', 'approved', 'suspended', 'rejected'].map(status => <div className="metric" key={status}><span style={{ textTransform: 'capitalize' }}>{status}</span><strong>{summary[status] ?? 0}</strong></div>)}</section>
        <div className="table-wrap"><table><thead><tr><th>Business</th><th>City</th><th>Status</th><th>Fields</th><th>Users</th><th>Reservations</th><th>Actions</th></tr></thead><tbody>{organizations.data.map(org => <tr key={org.id}><td><strong>{org.name}</strong><br /><small>{org.email} · {org.phone}</small></td><td>{org.city?.name}</td><td><Badge value={org.status} /></td><td>{org.football_fields_count}</td><td>{org.users_count}</td><td>{org.reservations_count}</td><td><div className="actions">{org.status !== 'approved' && <Button variant="success" onClick={() => setStatus(org.id, 'approved')}>Approve</Button>}{org.status !== 'suspended' && <Button variant="danger" onClick={() => setStatus(org.id, 'suspended')}>Suspend</Button>}{org.status === 'pending' && <Button variant="secondary" onClick={() => setStatus(org.id, 'rejected')}>Reject</Button>}</div></td></tr>)}</tbody></table></div><Pagination links={organizations.links} />
    </AppLayout>;
}
