import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_LABELS = {
    new: 'New', assigned: 'Assigned', contacted: 'Contacted',
    interested: 'Interested', follow_up: 'Follow-up',
    not_interested: 'Not Interested', converted: 'Converted',
};

function StatusBadge({ status }) {
    const slug = (status || '').replace(/_/g, '-');
    return <span className={`lead-status status-${slug}`}>{STATUS_LABELS[status] ?? status}</span>;
}

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

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Index({ leads, telecallers, totalLeads, newLeads, assignedLeads, followupToday, filters }) {
    const [form, setForm] = useState({
        search:     filters?.search     ?? '',
        telecaller: filters?.telecaller ?? '',
        status:     filters?.status     ?? '',
        date_range: filters?.date_range ?? '',
    });

    function applyFilter(e) {
        e.preventDefault();
        const params = {};
        if (form.search)     params.search     = form.search;
        if (form.telecaller) params.telecaller = form.telecaller;
        if (form.status)     params.status     = form.status;
        if (form.date_range) params.date_range = form.date_range;
        router.get('/manager/leads', params, { preserveState: false });
    }

    function reset() {
        setForm({ search: '', telecaller: '', status: '', date_range: '' });
        router.get('/manager/leads', {}, { preserveState: false });
    }

    const exportUrl = (extra = {}) => {
        const p = new URLSearchParams({ ...filters, ...extra });
        return `/manager/leads/export?${p}`;
    };

    return (
        <>
            <Head title="Lead Management" />

            {/* ── Toolbar ───────────────────────────────────────────────── */}
            <div className="d-flex align-items-center gap-2 flex-wrap mb-4">
                <div className="btn-group btn-group-sm" role="group">
                    <Link href="/manager/leads" className="btn btn-primary d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 15 }}>view_list</span>
                        List
                    </Link>
                    <Link href="/manager/leads/pipeline" className="btn btn-outline-primary d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 15 }}>view_kanban</span>
                        Pipeline
                    </Link>
                </div>

                <Link href="/manager/leads/create" className="btn btn-sm btn-primary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>add</span>
                    Add Lead
                </Link>

                <a href="/manager/leads/import" className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>upload_file</span>
                    Import Excel
                </a>

                <a href={exportUrl()} className="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>download</span>
                    Export Excel
                </a>

                <a href={exportUrl({ format: 'pdf' })} target="_blank" rel="noreferrer"
                    className="btn btn-sm btn-outline-danger d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>picture_as_pdf</span>
                    Export PDF
                </a>
            </div>

            {/* ── Stat cards ────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label: 'Total Leads',     value: totalLeads,    icon: 'groups',         color: '#6366f1' },
                    { label: 'New Leads',       value: newLeads,      icon: 'fiber_new',      color: '#10b981' },
                    { label: 'Assigned Leads',  value: assignedLeads, icon: 'assignment_ind', color: '#f59e0b' },
                    { label: 'Follow-up Today', value: followupToday, icon: 'event',          color: '#ef4444' },
                ].map(card => (
                    <div className="col-6 col-md-3" key={card.label}>
                        <div className="stat-card">
                            <div className="stat-icon" style={{ background: card.color + '22' }}>
                                <span className="material-icons" style={{ color: card.color }}>{card.icon}</span>
                            </div>
                            <div className="stat-label">{card.label}</div>
                            <div className="stat-value">{card.value}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Filters ───────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <div className="chart-header mb-3">
                    <h3>Filter Leads</h3>
                    <p>Refine by date, telecaller, status, and search terms</p>
                </div>
                <form onSubmit={applyFilter}>
                    <div className="row g-3">
                        <div className="col-md-2 col-6">
                            <select className="form-select" value={form.date_range}
                                onChange={e => setForm({ ...form, date_range: e.target.value })}>
                                <option value="">Date</option>
                                <option value="today">Today</option>
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                            </select>
                        </div>
                        <div className="col-md-3">
                            <select className="form-select" value={form.telecaller}
                                onChange={e => setForm({ ...form, telecaller: e.target.value })}>
                                <option value="">Telecaller</option>
                                {telecallers.map(t => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3">
                            <select className="form-select" value={form.status}
                                onChange={e => setForm({ ...form, status: e.target.value })}>
                                <option value="">Status</option>
                                {Object.entries(STATUS_LABELS).map(([v, l]) => (
                                    <option key={v} value={v}>{l}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-4">
                            <input className="form-control" type="text"
                                placeholder="Search Lead Code / Name / Phone / Email / Course / Source"
                                value={form.search}
                                onChange={e => setForm({ ...form, search: e.target.value })} />
                        </div>
                    </div>
                    <div className="mt-3 d-flex gap-2">
                        <button type="submit" className="btn btn-primary btn-sm px-3">Apply</button>
                        <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={reset}>Reset</button>
                    </div>
                </form>
            </div>

            {/* ── Table ─────────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>Lead List</h3>
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
                                <th>Source</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Next Follow-up</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data.length === 0 ? (
                                <tr><td colSpan={10} className="text-center py-4 text-muted">No Leads Found</td></tr>
                            ) : leads.data.map((lead, idx) => {
                                const sno = (leads.current_page - 1) * leads.per_page + idx + 1;
                                return (
                                    <tr key={lead.id}>
                                        <td>{sno}</td>
                                        <td>{lead.lead_code}</td>
                                        <td>
                                            <div className="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                                {lead.name}
                                                {lead.is_duplicate && (
                                                    <span style={{ background: '#fff7ed', color: '#ea580c', border: '1px solid #fed7aa', fontSize: 10, fontWeight: 600, padding: '2px 6px', borderRadius: 5 }}>
                                                        DUPLICATE
                                                    </span>
                                                )}
                                            </div>
                                            <div className="d-flex align-items-center gap-1 flex-wrap mt-1">
                                                <small className="text-muted">{lead.email || '—'}</small>
                                                <AgingBadge days={lead.days_aged} />
                                            </div>
                                        </td>
                                        <td><span className="fw-semibold">{lead.phone}</span></td>
                                        <td><span className="badge bg-light text-dark">{lead.source}</span></td>
                                        <td>{lead.course || '—'}</td>
                                        <td><StatusBadge status={lead.status} /></td>
                                        <td>{lead.assigned_user || '—'}</td>
                                        <td>{fmtDate(lead.next_followup)}</td>
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

                {/* ── Pagination ────────────────────────────────────────── */}
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
