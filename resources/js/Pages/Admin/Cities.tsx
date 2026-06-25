import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Field, Input, Modal } from '../../Components/UI';

export default function Cities({ cities }: { cities: any[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', country: 'XK' });
    return <AppLayout title="Cities"><Head title="Cities" /><div className="page-header"><div><h1>Cities</h1><p>Control the locations available during registration and public search.</p></div><Button onClick={() => setOpen(true)}><Plus size={18} />Add city</Button></div>
        <div className="table-wrap"><table><thead><tr><th>Name</th><th>Country</th><th>Status</th><th>Organizations</th><th>Fields</th><th>Action</th></tr></thead><tbody>{cities.map(city => <tr key={city.id}><td><strong>{city.name}</strong></td><td>{city.country}</td><td><Badge value={city.is_active ? 'active' : 'closed'} /></td><td>{city.organizations_count}</td><td>{city.football_fields_count}</td><td><Button variant="secondary" onClick={() => router.put(`/admin/cities/${city.id}`, { name: city.name, country: city.country, is_active: !city.is_active })}>{city.is_active ? 'Disable' : 'Enable'}</Button></td></tr>)}</tbody></table></div>
        <Modal open={open} title="Add city" onClose={() => setOpen(false)}><form onSubmit={e => { e.preventDefault(); form.post('/admin/cities', { onSuccess: () => { setOpen(false); form.reset(); } }); }}><div className="form-grid"><Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></Field><Field label="Country code" error={form.errors.country}><Input maxLength={2} value={form.data.country} onChange={e => form.setData('country', e.target.value.toUpperCase())} /></Field></div><div className="form-actions"><Button type="button" variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button disabled={form.processing}>Save</Button></div></form></Modal>
    </AppLayout>;
}
