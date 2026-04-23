import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ─── Aging badge — mirrors resources/views/components/aging-badge.blade.php ──
function AgingBadge({ days }) {
    if (days >= 6) {
        return (
            <span style={{
                background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca',
                fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 6,
            }}>
                {days}d old
            </span>
        );
    }
    if (days >= 3) {
        return (
            <span style={{
                background: '#fffbeb', color: '#d97706', border: '1px solid #fde68a',
                fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 6,
            }}>
                {days}d old
            </span>
        );
    }
    return null;
}

// ─── Status badge — uses existing .lead-status CSS classes from style.css ────
const STATUS_LABELS = {
    new:            'New',
    contacted:      'Contacted',
    interested:     'Interested',
    follow_up:      'Follow-up',
    not_interested: 'Not Interested',
    converted:      'Converted',
    lost:           'Lost',
};

function StatusBadge({ status }) {
    const slug = (status || '').replace(/_/g, '-');
    return (
        <span className={`lead-status status-${slug}`}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

// ─── Format date: "09 Apr 2026" style ────────────────────────────────────────
function fmtDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

// ─── Main page component ──────────────────────────────────────────────────────
export default function Index({ stats, leads, filters }) {
    const s = stats ?? {};

    // Filter form state — initialised from current URL query params
    const [form, setForm] = useState({
        search:     filters?.search     ?? '',
        status:     filters?.status     ?? '',
        date_range: filters?.date_range ?? '',
    });

    // Live call count — polled every 45 s from the panel snapshot endpoint
    const [activeCalls, setActiveCalls] = useState(s.active_calls ?? 0);
    const [callStatus,  setCallStatus]  = useState(
        (s.active_calls ?? 0) > 0 ? 'On Call' : 'Idle'
    );

    useEffect(() => {
        function poll() {
            fetch('/telecaller/panel/snapshot', { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data?.ok) return;
                    const n = Number(data.active_call_count || 0);
                    setActiveCalls(n);
                    setCallStatus(data.call_status || (n > 0 ? 'On Call' : 'Idle'));
                })
                .catch(() => {});
        }
        poll();
        const t = setInterval(poll, 45_000);
        return () => clearInterval(t);
    }, []);

    // Filter form handlers
    function handleFilter(e) {
        e.preventDefault();
        // Strip empty values so the URL stays clean
        const params = {};
        if (form.search)     params.search     = form.search;
        if (form.status)     params.status     = form.status;
        if (form.date_range) params.date_range = form.date_range;
        router.get('/telecaller/leads', params, { preserveState: false });
    }

    function resetFilter() {
        setForm({ search: '', status: '', date_range: '' });
        router.get('/telecaller/leads', {}, { preserveState: false });
    }

    // Stat card config
    const CARDS = [
        { label: 'Total Leads',     value: s.total,      icon: 'groups',    color: '#6366f1' },
        { label: 'New Leads',       value: s.new,        icon: 'fiber_new', color: '#10b981' },
        { label: 'Interested',      value: s.interested, icon: 'thumb_up',  color: '#f59e0b' },
        { label: 'Follow-up Today', value: s.followup,   icon: 'event',     color: '#06b6d4' },
    ];

    return (
        <>
            <Head title="My Leads" />

            {/* ── View-toggle + call badge (replaces @section('header_actions')) ── */}
            <div className="d-flex align-items-center gap-2 flex-wrap mb-4">
                <div className="btn-group btn-group-sm" role="group">
                    <Link href="/telecaller/leads"
                        className="btn btn-primary d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 15 }}>view_list</span>
                        List
                    </Link>
                    <Link href="/telecaller/leads/pipeline"
                        className="btn btn-outline-primary d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 15 }}>view_kanban</span>
                        Pipeline
                    </Link>
                </div>

                <span className="badge rounded-pill text-bg-light border px-3 py-2 d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 15 }}>call</span>
                    {callStatus}
                </span>
                <span className={`badge rounded-pill px-3 py-2 ${activeCalls > 0 ? 'text-bg-danger' : 'text-bg-success'}`}>
                    Active Calls: {activeCalls}
                </span>
            </div>

            {/* ── Stat cards ────────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {CARDS.map(card => (
                    <div className="col-6 col-md-3" key={card.label}>
                        <div className="stat-card">
                            <div className="stat-icon" style={{ background: card.color + '22' }}>
                                <span className="material-icons" style={{ color: card.color }}>
                                    {card.icon}
                                </span>
                            </div>
                            <div className="stat-label">{card.label}</div>
                            <div className="stat-value">{card.value ?? '—'}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Filter form ───────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <div className="chart-header mb-3">
                    <h3>Filter My Leads</h3>
                    <p>Refine by date, status, and lead details</p>
                </div>
                <form onSubmit={handleFilter}>
                    <div className="row g-3">
                        <div className="col-md-3">
                            <select className="form-select" value={form.date_range}
                                onChange={e => setForm({ ...form, date_range: e.target.value })}>
                                <option value="">Date</option>
                                <option value="today">Today</option>
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                            </select>
                        </div>
                        <div className="col-md-5">
                            <input className="form-control" type="text"
                                placeholder="Search Lead Code / Name / Phone"
                                value={form.search}
                                onChange={e => setForm({ ...form, search: e.target.value })} />
                        </div>
                        <div className="col-md-4">
                            <select className="form-select" value={form.status}
                                onChange={e => setForm({ ...form, status: e.target.value })}>
                                <option value="">Status</option>
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="interested">Interested</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="not_interested">Not Interested</option>
                            </select>
                        </div>
                    </div>
                    <div className="mt-3 d-flex gap-2">
                        <button type="submit" className="btn btn-primary btn-sm px-3">Apply</button>
                        <button type="button" className="btn btn-outline-secondary btn-sm px-3"
                            onClick={resetFilter}>Reset</button>
                    </div>
                </form>
            </div>

            {/* ── Leads table ───────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>My Lead List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>
                        {leads.total} records
                    </span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Lead Code</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Next Follow-up</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-4 text-muted">
                                        No Leads Found
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
                                            </div>
                                            <div className="d-flex align-items-center gap-1 flex-wrap mt-1">
                                                <small className="text-muted">{lead.email || '—'}</small>
                                                <AgingBadge days={lead.days_aged} />
                                            </div>
                                        </td>
                                        <td><span className="fw-semibold">{lead.phone}</span></td>
                                        <td>{lead.course || '—'}</td>
                                        <td><StatusBadge status={lead.status} /></td>
                                        <td>{fmtDate(lead.next_followup)}</td>
                                        <td>
                                            <Link
                                                href={`/telecaller/leads/${lead.encrypted_id}`}
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

                {/* ── Pagination ──────────────────────────────────────────── */}
                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {leads.from ?? 0}–{leads.to ?? 0} of {leads.total} results
                    </small>
                    {leads.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {leads.links.map((link, i) => (
                                    <li key={i}
                                        className={[
                                            'page-item',
                                            link.active  ? 'active'   : '',
                                            !link.url    ? 'disabled' : '',
                                        ].join(' ')}>
                                        {link.url ? (
                                            <Link href={link.url} className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ) : (
                                            <span className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
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
