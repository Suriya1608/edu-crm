import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

// ─── Relative time helper ─────────────────────────────────────────────────────
function relativeTime(isoStr) {
    if (!isoStr) return null;
    const diffMs  = Date.now() - new Date(isoStr).getTime();
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1)   return 'Just now';
    if (diffMin < 60)  return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24)   return `${diffHr}h ago`;
    const diffDay = Math.floor(diffHr / 24);
    if (diffDay < 30)  return `${diffDay}d ago`;
    return new Date(isoStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

// ─── Aging badge — hot (fresh) → warning → danger ────────────────────────────
function AgingBadge({ days }) {
    if (days >= 6) return (
        <span style={{ background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca',
            fontSize: 10.5, fontWeight: 700, padding: '2px 7px', borderRadius: 6 }}>
            {days}d old
        </span>
    );
    if (days >= 3) return (
        <span style={{ background: '#fffbeb', color: '#d97706', border: '1px solid #fde68a',
            fontSize: 10.5, fontWeight: 700, padding: '2px 7px', borderRadius: 6 }}>
            {days}d old
        </span>
    );
    // 0-2 days → hot / fresh lead
    return (
        <span style={{ background: '#f0fdf4', color: '#16a34a', border: '1px solid #bbf7d0',
            fontSize: 10.5, fontWeight: 700, padding: '2px 7px', borderRadius: 6,
            display: 'inline-flex', alignItems: 'center', gap: 2 }}>
            <span className="material-icons" style={{ fontSize: 10 }}>local_fire_department</span>
            Hot
        </span>
    );
}

// ─── Status badge ─────────────────────────────────────────────────────────────
const STATUS_LABELS = {
    new: 'New', assigned: 'Assigned', contacted: 'Contacted', interested: 'Interested',
    follow_up: 'Follow-up', not_interested: 'Not Interested',
    converted: 'Converted', lost: 'Lost',
};
function StatusBadge({ status }) {
    return (
        <span className={`lead-status status-${(status || '').replace(/_/g, '-')}`}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

// ─── Follow-up date cell ──────────────────────────────────────────────────────
function FollowupCell({ dateStr }) {
    if (!dateStr) return <span className="text-muted">—</span>;
    const due   = new Date(dateStr);
    const today = new Date();
    due.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    const diff  = Math.round((due - today) / 86400000);
    const label = due.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    if (diff < 0) return (
        <div>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#dc2626', background: '#fef2f2',
                border: '1px solid #fecaca', padding: '2px 8px', borderRadius: 6,
                display: 'inline-flex', alignItems: 'center', gap: 3 }}>
                <span className="material-icons" style={{ fontSize: 12 }}>schedule</span>{label}
            </span>
            <div style={{ fontSize: 10.5, color: '#ef4444', fontWeight: 600, marginTop: 2 }}>Overdue</div>
        </div>
    );
    if (diff === 0) return (
        <div>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#d97706', background: '#fffbeb',
                border: '1px solid #fde68a', padding: '2px 8px', borderRadius: 6,
                display: 'inline-flex', alignItems: 'center', gap: 3 }}>
                <span className="material-icons" style={{ fontSize: 12 }}>today</span>{label}
            </span>
            <div style={{ fontSize: 10.5, color: '#d97706', fontWeight: 600, marginTop: 2 }}>Today</div>
        </div>
    );
    return <span style={{ fontSize: 13, color: '#475569' }}>{label}</span>;
}

// ─── Last Activity cell ───────────────────────────────────────────────────────
const ACTIVITY_META = {
    call:          { icon: 'call',        color: '#6366f1', label: 'Call'     },
    note:          { icon: 'sticky_note_2', color: '#06b6d4', label: 'Note'  },
    status_change: { icon: 'sync_alt',    color: '#f59e0b', label: 'Status'   },
    email:         { icon: 'email',       color: '#8b5cf6', label: 'Email'    },
    followup:      { icon: 'event',       color: '#10b981', label: 'Follow-up'},
    assignment:    { icon: 'person_add',  color: '#0891b2', label: 'Assigned' },
};

function LastActivityCell({ type, isoStr }) {
    if (!isoStr) return <span className="text-muted" style={{ fontSize: 12 }}>—</span>;
    const meta = ACTIVITY_META[type] ?? { icon: 'history', color: '#94a3b8', label: type ?? 'Activity' };
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span className="material-icons"
                style={{ fontSize: 15, color: meta.color, flexShrink: 0 }}>
                {meta.icon}
            </span>
            <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: '#334155', lineHeight: 1.2 }}>
                    {meta.label}
                </div>
                <div style={{ fontSize: 11, color: '#94a3b8' }}>{relativeTime(isoStr)}</div>
            </div>
        </div>
    );
}

// ─── Quick call icon button (action column) ───────────────────────────────────
function QuickCallBtn({ phone, leadId }) {
    const [calling, setCalling] = useState(false);

    async function handleCall(e) {
        e.stopPropagation();
        if (!phone || calling) return;
        setCalling(true);
        try { await window.GC?.startCall(phone, leadId); } catch (_) {}
        setTimeout(() => setCalling(false), 3000);
    }

    if (!phone) return null;
    return (
        <button type="button" onClick={handleCall} title={`Call ${phone}`}
            style={{ width: 30, height: 30, borderRadius: '50%', border: 'none', flexShrink: 0,
                background: calling ? '#e0e7ff' : '#dcfce7', cursor: 'pointer',
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                transition: 'background .2s' }}>
            <span className="material-icons"
                style={{ fontSize: 16, color: calling ? '#6366f1' : '#16a34a' }}>
                {calling ? 'phone_in_talk' : 'call'}
            </span>
        </button>
    );
}

// ─── Empty state ──────────────────────────────────────────────────────────────
function EmptyState() {
    return (
        <tr>
            <td colSpan={10}>
                <div style={{ textAlign: 'center', padding: '52px 0 44px' }}>
                    <div style={{ width: 72, height: 72, borderRadius: 20, background: '#eef2ff',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        margin: '0 auto 16px' }}>
                        <span className="material-icons" style={{ fontSize: 36, color: '#a5b4fc' }}>person_search</span>
                    </div>
                    <div style={{ fontSize: 15, fontWeight: 700, color: '#334155', marginBottom: 6 }}>
                        No leads found
                    </div>
                    <div style={{ fontSize: 13, color: '#94a3b8', maxWidth: 280, margin: '0 auto' }}>
                        Try adjusting your filters, or check back once new leads are assigned.
                    </div>
                </div>
            </td>
        </tr>
    );
}

// ─── Sortable column header ───────────────────────────────────────────────────
function SortHeader({ children, field, sort, sortDir, allFilters, style }) {
    const isActive = sort === field;
    const nextDir  = isActive && sortDir === 'asc' ? 'desc' : 'asc';

    function handleSort() {
        const params = { ...allFilters, sort: field, sort_dir: nextDir };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get('/telecaller/leads', params, { preserveState: false });
    }

    return (
        <th onClick={handleSort} style={{ cursor: 'pointer', userSelect: 'none', ...style }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, whiteSpace: 'nowrap' }}>
                {children}
                <span className="material-icons"
                    style={{ fontSize: 13, color: isActive ? '#6366f1' : '#cbd5e1', transition: 'color .15s' }}>
                    {isActive ? (sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}
                </span>
            </span>
        </th>
    );
}

// ─── Stat card config ─────────────────────────────────────────────────────────
const CARDS = [
    { label: 'Total Leads',        icon: 'groups',        grad: 'blue'   },
    { label: 'New Leads',          icon: 'fiber_new',     grad: 'green'  },
    { label: 'Interested',         icon: 'thumb_up',      grad: 'amber'  },
    { label: 'Follow-up Today',    icon: 'event',         grad: 'cyan'   },
    { label: 'Overdue Follow-ups', icon: 'alarm',         grad: 'red'    },
    { label: 'Converted (Month)',  icon: 'check_circle',  grad: 'purple' },
];

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ stats, leads, filters, courses, sources }) {
    const s = stats ?? {};

    const sort    = filters?.sort     ?? '';
    const sortDir = filters?.sort_dir ?? 'desc';

    const [form, setForm] = useState({
        search:     filters?.search     ?? '',
        status:     filters?.status     ?? '',
        date_range: filters?.date_range ?? '',
        course_id:  filters?.course_id  ?? '',
        source:     filters?.source     ?? '',
    });

    const [selectedIds, setSelectedIds] = useState(new Set());
    const [bulkStatus, setBulkStatus]   = useState('');
    const [bulkLoading, setBulkLoading] = useState(false);

    function buildParams(overrides = {}) {
        const base = {};
        if (form.search)        base.search     = form.search;
        if (form.status)        base.status     = form.status;
        if (form.date_range)    base.date_range = form.date_range;
        if (form.course_id)     base.course_id  = form.course_id;
        if (form.source)        base.source     = form.source;
        if (sort)               base.sort       = sort;
        if (sortDir)            base.sort_dir   = sortDir;
        if (filters?.per_page)  base.per_page   = filters.per_page;
        return { ...base, ...overrides };
    }

    function toggleSelect(id) {
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function toggleSelectAll() {
        if (selectedIds.size === leads.data.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(leads.data.map(l => l.id)));
        }
    }

    async function applyBulkStatus() {
        if (!bulkStatus || selectedIds.size === 0) return;
        setBulkLoading(true);
        try {
            const res = await fetch('/telecaller/leads/bulk-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ ids: [...selectedIds], status: bulkStatus }),
            });
            if (res.ok) {
                setSelectedIds(new Set());
                setBulkStatus('');
                router.reload({ preserveScroll: true });
            }
        } finally {
            setBulkLoading(false);
        }
    }

    function handleFilter(e) {
        e.preventDefault();
        router.get('/telecaller/leads', buildParams(), { preserveState: false });
    }

    function exportUrl(format) {
        const p = new URLSearchParams({ format });
        const params = buildParams();
        Object.entries(params).forEach(([k, v]) => p.set(k, v));
        return `/telecaller/leads/export?${p.toString()}`;
    }

    function resetFilter() {
        setForm({ search: '', status: '', date_range: '', course_id: '', source: '' });
        router.get('/telecaller/leads', {}, { preserveState: false });
    }

    function changePerPage(value) {
        router.get('/telecaller/leads', buildParams({ per_page: value }), { preserveState: false });
    }

    const statValues = [s.total, s.new, s.interested, s.followup, s.overdue, s.converted_month];

    return (
        <>
            <Head title="My Leads" />

            {/* ── View toggle ─────────────────────────────────────────────── */}
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
            </div>

            {/* ── Stat cards ───────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {CARDS.map((card, i) => (
                    <div className="col-6 col-md-2" key={card.label}>
                        <div className="stat-card">
                            <div className={`stat-icon ${card.grad}`}>
                                <span className="material-icons">{card.icon}</span>
                            </div>
                            <div className="stat-label">{card.label}</div>
                            <div className="stat-value">{statValues[i] ?? '—'}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Filter panel ─────────────────────────────────────────────── */}
            <div className="card border-0 shadow-sm mb-3" style={{ borderRadius: 14 }}>
                <div className="card-body" style={{ padding: '20px 24px' }}>
                    <div className="mb-3">
                        <div style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>Filter Leads</div>
                        <div style={{ fontSize: 12, color: '#64748b' }}>Refine by date, status, and lead details</div>
                    </div>
                    <form onSubmit={handleFilter}>
                        <div className="row g-2">
                            <div className="col-md-4">
                                <input className="form-control" type="text"
                                    placeholder="Search by name, phone or lead code…"
                                    value={form.search}
                                    onChange={e => setForm({ ...form, search: e.target.value })} />
                            </div>
                            <div className="col-md-2">
                                <select className="form-select" value={form.status}
                                    onChange={e => setForm({ ...form, status: e.target.value })}>
                                    <option value="">All Statuses</option>
                                    <option value="new">New</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="interested">Interested</option>
                                    <option value="follow_up">Follow-up</option>
                                    <option value="not_interested">Not Interested</option>
                                    <option value="converted">Converted</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div className="col-md-2">
                                <select className="form-select" value={form.date_range}
                                    onChange={e => setForm({ ...form, date_range: e.target.value })}>
                                    <option value="">Date Range</option>
                                    <option value="today">Today</option>
                                    <option value="7">Last 7 Days</option>
                                    <option value="30">Last 30 Days</option>
                                </select>
                            </div>
                            <div className="col-md-2">
                                <select className="form-select" value={form.course_id}
                                    onChange={e => setForm({ ...form, course_id: e.target.value })}>
                                    <option value="">All Courses</option>
                                    {(courses ?? []).map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-md-2">
                                <select className="form-select" value={form.source}
                                    onChange={e => setForm({ ...form, source: e.target.value })}>
                                    <option value="">All Sources</option>
                                    {(sources ?? []).map(s => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="mt-3 d-flex gap-2 flex-wrap align-items-center">
                            <button type="submit" className="btn btn-primary btn-sm px-3">
                                <span className="material-icons me-1" style={{ fontSize: 14, verticalAlign: 'middle' }}>search</span>
                                Apply
                            </button>
                            <button type="button" className="btn btn-outline-secondary btn-sm px-3"
                                onClick={resetFilter}>Reset</button>
                            <div className="dropdown ms-auto">
                                <button type="button"
                                    className="btn btn-outline-success btn-sm px-3 d-flex align-items-center gap-1"
                                    data-bs-toggle="dropdown">
                                    <span className="material-icons" style={{ fontSize: 15 }}>download</span>
                                    Export
                                </button>
                                <ul className="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a className="dropdown-item d-flex align-items-center gap-2"
                                            href={exportUrl('excel')} target="_blank" rel="noreferrer">
                                            <span className="material-icons" style={{ fontSize: 16, color: '#10b981' }}>table_view</span>
                                            Excel (.xlsx)
                                        </a>
                                    </li>
                                    <li>
                                        <a className="dropdown-item d-flex align-items-center gap-2"
                                            href={exportUrl('pdf')} target="_blank" rel="noreferrer">
                                            <span className="material-icons" style={{ fontSize: 16, color: '#ef4444' }}>picture_as_pdf</span>
                                            PDF
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {/* ── Leads table ───────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>My Lead List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>{leads.total} records</span>
                </div>

                {/* ── Bulk action bar ─────────────────────────────────────────── */}
                {selectedIds.size > 0 && (
                    <div style={{ background: '#eef2ff', borderBottom: '1px solid #c7d2fe',
                        padding: '10px 20px', display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                        <span style={{ fontSize: 13, fontWeight: 600, color: '#4f46e5' }}>
                            {selectedIds.size} lead{selectedIds.size > 1 ? 's' : ''} selected
                        </span>
                        <select className="form-select form-select-sm" style={{ width: 180 }}
                            value={bulkStatus}
                            onChange={e => setBulkStatus(e.target.value)}>
                            <option value="">Change status to…</option>
                            <option value="new">New</option>
                            <option value="assigned">Assigned</option>
                            <option value="contacted">Contacted</option>
                            <option value="interested">Interested</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="not_interested">Not Interested</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                        <button type="button" className="btn btn-primary btn-sm px-3"
                            disabled={!bulkStatus || bulkLoading}
                            onClick={applyBulkStatus}>
                            {bulkLoading ? 'Updating…' : 'Apply'}
                        </button>
                        <button type="button" className="btn btn-outline-secondary btn-sm"
                            onClick={() => setSelectedIds(new Set())}>
                            Clear
                        </button>
                    </div>
                )}

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th style={{ width: 36 }}>
                                    <input type="checkbox"
                                        checked={leads.data.length > 0 && selectedIds.size === leads.data.length}
                                        onChange={toggleSelectAll}
                                        title="Select all on this page" />
                                </th>
                                <th style={{ width: 44 }}>S.No</th>
                                <th>Lead Code</th>
                                <SortHeader field="name" sort={sort} sortDir={sortDir}
                                    allFilters={buildParams()}>Name</SortHeader>
                                <th>Phone</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <SortHeader field="next_followup" sort={sort} sortDir={sortDir}
                                    allFilters={buildParams()}>Next Follow-up</SortHeader>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data.length === 0 ? <EmptyState /> : leads.data.map((lead, idx) => {
                                const sno  = (leads.current_page - 1) * leads.per_page + idx + 1;
                                const href = `/telecaller/leads/${lead.encrypted_id}`;
                                const isSelected = selectedIds.has(lead.id);
                                return (
                                    <tr key={lead.id}
                                        onClick={() => router.visit(href)}
                                        style={{ cursor: 'pointer',
                                            background: isSelected ? '#eef2ff' : undefined }}
                                        onMouseEnter={e => { if (!isSelected) e.currentTarget.style.background = '#f8faff'; }}
                                        onMouseLeave={e => { e.currentTarget.style.background = isSelected ? '#eef2ff' : ''; }}>
                                        <td onClick={e => e.stopPropagation()}>
                                            <input type="checkbox" checked={isSelected}
                                                onChange={() => toggleSelect(lead.id)} />
                                        </td>
                                        <td>{sno}</td>
                                        <td>
                                            <span style={{ fontFamily: 'monospace', fontSize: 12,
                                                background: '#f1f5f9', padding: '2px 7px', borderRadius: 5 }}>
                                                {lead.lead_code}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                                {lead.name}
                                                <AgingBadge days={lead.days_aged} />
                                            </div>
                                            <small className="text-muted">{lead.email || '—'}</small>
                                        </td>
                                        <td>
                                            <span style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>
                                                {lead.phone || '—'}
                                            </span>
                                        </td>
                                        <td>{lead.course || '—'}</td>
                                        <td><StatusBadge status={lead.status} /></td>
                                        <td>
                                            <LastActivityCell
                                                type={lead.last_activity_type}
                                                isoStr={lead.last_activity_at} />
                                        </td>
                                        <td><FollowupCell dateStr={lead.next_followup} /></td>
                                        <td onClick={e => e.stopPropagation()}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                <QuickCallBtn phone={lead.phone} leadId={lead.id} />
                                                <Link href={href} className="btn btn-sm btn-outline-primary">
                                                    View
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* ── Pagination ──────────────────────────────────────────── */}
                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div className="d-flex align-items-center gap-3 flex-wrap">
                        <small className="text-muted">
                            Showing {leads.from ?? 0}–{leads.to ?? 0} of {leads.total} results
                        </small>
                        <div className="d-flex align-items-center gap-1">
                            <small className="text-muted">Per page:</small>
                            <select className="form-select form-select-sm" style={{ width: 70 }}
                                value={filters?.per_page || '15'}
                                onChange={e => changePerPage(e.target.value)}>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                    {leads.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {leads.links.map((link, i) => (
                                    <li key={i} className={[
                                        'page-item',
                                        link.active ? 'active' : '',
                                        !link.url   ? 'disabled' : '',
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
