import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function AgingBadge({ days }) {
    if (days >= 6) return (
        <span style={{ background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca', fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 6 }}>
            {days}d old
        </span>
    );
    if (days >= 3) return (
        <span style={{ background: '#fffbeb', color: '#d97706', border: '1px solid #fde68a', fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 6 }}>
            {days}d old
        </span>
    );
    return null;
}

const STATUS_COLORS = {
    new:            { bg: '#eff6ff', color: '#2563eb' },
    assigned:       { bg: '#f0f9ff', color: '#0891b2' },
    contacted:      { bg: '#f8fafc', color: '#475569' },
    interested:     { bg: '#f0fdf4', color: '#16a34a' },
    not_interested: { bg: '#fef2f2', color: '#dc2626' },
    converted:      { bg: '#1e293b', color: '#fff' },
    follow_up:      { bg: '#fffbeb', color: '#92400e' },
};

function StatusBadge({ status }) {
    const s = STATUS_COLORS[status] ?? { bg: '#f8fafc', color: '#475569' };
    return (
        <span className="badge" style={{ background: s.bg, color: s.color, padding: '4px 10px', borderRadius: 6, fontSize: 12, fontWeight: 600 }}>
            {status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
        </span>
    );
}

export default function Duplicates({ leads, filters }) {
    const [search, setSearch] = useState(filters?.search ?? '');

    function applySearch(e) {
        e.preventDefault();
        router.get('/manager/leads/duplicates', search ? { search } : {}, { preserveState: false });
    }

    function reset() {
        setSearch('');
        router.get('/manager/leads/duplicates', {}, { preserveState: false });
    }

    return (
        <>
            <Head title="Duplicate Leads" />

            <div className="chart-card mb-3">
                <div className="chart-header mb-3">
                    <div>
                        <h3>Duplicate Leads</h3>
                        <p className="text-muted mb-0" style={{ fontSize: 13 }}>
                            Leads sharing the same mobile number or email address.
                        </p>
                    </div>
                </div>
                <form onSubmit={applySearch} className="d-flex gap-2 flex-wrap">
                    <input type="text" className="form-control" style={{ maxWidth: 320 }}
                        placeholder="Search Lead Code / Name / Phone / Email"
                        value={search} onChange={e => setSearch(e.target.value)} />
                    <button type="submit" className="btn btn-primary btn-sm px-3">Search</button>
                    <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={reset}>Reset</button>
                </form>
            </div>

            <div className="custom-table">
                <div className="table-header">
                    <h3>Duplicate Lead List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>{leads.total} records</span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Lead Code</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-center py-5 text-muted">
                                        <span className="material-icons d-block mb-2" style={{ fontSize: 40, opacity: 0.3 }}>content_copy</span>
                                        No duplicate leads found
                                    </td>
                                </tr>
                            ) : leads.data.map((lead, idx) => {
                                const sno = (leads.current_page - 1) * leads.per_page + idx + 1;
                                return (
                                    <tr key={lead.id}>
                                        <td>{sno}</td>
                                        <td>{lead.lead_code}</td>
                                        <td>
                                            <div className="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                                {lead.name}
                                                <span style={{ background: '#fff7ed', color: '#ea580c', border: '1px solid #fed7aa', fontSize: 10, fontWeight: 600, padding: '2px 6px', borderRadius: 5 }}>
                                                    DUPLICATE
                                                </span>
                                            </div>
                                            <AgingBadge days={lead.days_aged} />
                                        </td>
                                        <td><span className="fw-semibold">{lead.phone}</span></td>
                                        <td>{lead.email || '—'}</td>
                                        <td><span className="badge bg-light text-dark">{lead.source}</span></td>
                                        <td><StatusBadge status={lead.status} /></td>
                                        <td>{lead.assigned_user || '—'}</td>
                                        <td>{lead.created_at}</td>
                                        <td>
                                            <Link href={`/manager/leads/${lead.encrypted_id}`}
                                                className="btn btn-sm btn-outline-primary">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {leads.from ?? 0}–{leads.to ?? 0} of {leads.total} results
                    </small>
                    {leads.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {leads.links.map((link, i) => (
                                    <li key={i} className={['page-item', link.active ? 'active' : '', !link.url ? 'disabled' : ''].join(' ')}>
                                        {link.url
                                            ? <Link href={link.url} className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                            : <span className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                        }
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}
                </div>
            </div>
        </>
    );
}
