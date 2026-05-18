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

function SlaLevelBadge({ level, escalated }) {
    if (level >= 2) return (
        <span style={{ background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca', fontSize: 10, fontWeight: 700, padding: '2px 7px', borderRadius: 6, letterSpacing: '0.3px' }}>
            SLA L2
        </span>
    );
    if (level >= 1) return (
        <span style={{ background: '#fff7ed', color: '#ea580c', border: '1px solid #fed7aa', fontSize: 10, fontWeight: 700, padding: '2px 7px', borderRadius: 6, letterSpacing: '0.3px' }}>
            SLA L1
        </span>
    );
    if (escalated) return (
        <span style={{ background: '#fefce8', color: '#ca8a04', border: '1px solid #fde68a', fontSize: 10, fontWeight: 700, padding: '2px 7px', borderRadius: 6, letterSpacing: '0.3px' }}>
            ESCALATED
        </span>
    );
    return null;
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

function EditContactModal({ lead, urls, onSaved, onClose }) {
    const [phone, setPhone] = useState(lead.phone || '');
    const [email, setEmail] = useState(lead.email || '');
    const [err, setErr]     = useState('');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function handleSubmit(e) {
        e.preventDefault(); setErr('');
        const res = await fetch(urls.update_contact, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ phone, email }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) { setErr(data.message || 'Failed to update.'); return; }
        onSaved({ phone, email });
        onClose();
    }

    return (
        <div className="modal fade show" style={{ display: 'block', background: 'rgba(0,0,0,.4)' }} tabIndex={-1}>
            <div className="modal-dialog">
                <form onSubmit={handleSubmit}>
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title">
                                <span className="material-icons me-2" style={{ verticalAlign: -5, color: '#6366f1' }}>edit</span>
                                Edit Contact Details
                            </h5>
                            <button type="button" className="btn-close" onClick={onClose} />
                        </div>
                        <div className="modal-body">
                            {err && <div className="alert alert-danger py-2" style={{ fontSize: 13 }}>{err}</div>}
                            <div className="mb-3">
                                <label className="form-label fw-semibold">Mobile Number <span className="text-danger">*</span></label>
                                <input type="text" className="form-control" required maxLength={20}
                                    value={phone} onChange={e => setPhone(e.target.value)} placeholder="e.g. 9876543210" />
                            </div>
                            <div className="mb-3">
                                <label className="form-label fw-semibold">Email Address</label>
                                <input type="email" className="form-control" maxLength={255}
                                    value={email} onChange={e => setEmail(e.target.value)} placeholder="e.g. student@example.com" />
                            </div>
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
                            <button type="submit" className="btn btn-primary">
                                <span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: -3 }}>save</span>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

const EMPTY_FORM = {
    search: '', telecaller: '', status: '', date_range: '',
    date_from: '', date_to: '',
    course_id: '', academic_year_id: '', quota: '', source: '', gender: '',
    state: '', city: '', district: '',
    followup: '', no_activity_days: '',
    sla: '', is_duplicate: '', is_active: '',
    aged_min: '', aged_max: '',
};

function hasAdvancedFilter(form) {
    const adv = ['course_id', 'academic_year_id', 'quota', 'source', 'gender',
        'state', 'city', 'district', 'followup', 'no_activity_days',
        'sla', 'is_duplicate', 'is_active', 'aged_min', 'aged_max'];
    return adv.some(k => form[k] !== '' && form[k] !== null && form[k] !== undefined);
}

export default function Index({ leads, telecallers, courses, academicYears, sources, totalLeads, newLeads, assignedLeads, followupToday, filters }) {
    const [editTarget, setEditTarget] = useState(null);
    const [leadsState, setLeadsState] = useState(leads);
    const [form, setForm] = useState({ ...EMPTY_FORM, ...filters });
    const [showAdvanced, setShowAdvanced] = useState(() => hasAdvancedFilter({ ...EMPTY_FORM, ...filters }));

    function set(key, val) { setForm(prev => ({ ...prev, [key]: val })); }

    function applyFilter(e) {
        e.preventDefault();
        const params = {};
        Object.entries(form).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
        router.get('/manager/leads', params, { preserveState: false });
    }

    function reset() {
        setForm(EMPTY_FORM);
        router.get('/manager/leads', {}, { preserveState: false });
    }

    const activeCount = Object.entries(form).filter(([, v]) => v !== '' && v !== null).length;

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

                <a href="/manager/leads/import"
                    onClick={e => { e.preventDefault(); window.location.href = '/manager/leads/import'; }}
                    className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>upload_file</span>
                    Import Excel
                </a>

                <a href={exportUrl()}
                    onClick={e => { e.preventDefault(); window.location.href = exportUrl(); }}
                    className="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>download</span>
                    Export Excel
                </a>

                <a href={exportUrl({ format: 'pdf' })}
                    onClick={e => { e.preventDefault(); window.location.href = exportUrl({ format: 'pdf' }); }}
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
                    <div>
                        <h3>Filter Leads</h3>
                        <p>Refine your leads with basic and advanced filters</p>
                    </div>
                    {activeCount > 0 && (
                        <span style={{ background: '#6366f1', color: '#fff', fontSize: 12, fontWeight: 600, padding: '3px 10px', borderRadius: 20 }}>
                            {activeCount} active
                        </span>
                    )}
                </div>
                <form onSubmit={applyFilter}>
                    {/* Basic row */}
                    <div className="row g-2 mb-2">
                        <div className="col-md-4">
                            <input className="form-control form-control-sm" type="text"
                                placeholder="Search code / name / phone / email / source"
                                value={form.search}
                                onChange={e => set('search', e.target.value)} />
                        </div>
                        <div className="col-md-2">
                            <select className="form-select form-select-sm" value={form.telecaller} onChange={e => set('telecaller', e.target.value)}>
                                <option value="">All Telecallers</option>
                                {telecallers.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                        </div>
                        <div className="col-md-2">
                            <select className="form-select form-select-sm" value={form.status} onChange={e => set('status', e.target.value)}>
                                <option value="">All Statuses</option>
                                {Object.entries(STATUS_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                        <div className="col-md-2">
                            <select className="form-select form-select-sm" value={form.date_range} onChange={e => set('date_range', e.target.value)}>
                                <option value="">Any Date</option>
                                <option value="today">Today</option>
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        {form.date_range === 'custom' && (
                            <>
                                <div className="col-md-1">
                                    <input type="date" className="form-control form-control-sm" value={form.date_from}
                                        onChange={e => set('date_from', e.target.value)} placeholder="From" title="From date" />
                                </div>
                                <div className="col-md-1">
                                    <input type="date" className="form-control form-control-sm" value={form.date_to}
                                        onChange={e => set('date_to', e.target.value)} placeholder="To" title="To date" />
                                </div>
                            </>
                        )}
                    </div>

                    {/* Advanced toggle */}
                    <div className="mb-2">
                        <button type="button"
                            className={`btn btn-sm ${showAdvanced ? 'btn-outline-primary' : 'btn-outline-secondary'} d-inline-flex align-items-center gap-1`}
                            style={{ fontSize: 12 }}
                            onClick={() => setShowAdvanced(v => !v)}>
                            <span className="material-icons" style={{ fontSize: 14 }}>{showAdvanced ? 'expand_less' : 'tune'}</span>
                            {showAdvanced ? 'Hide Advanced Filters' : 'Advanced Filters'}
                            {hasAdvancedFilter(form) && (
                                <span style={{ background: '#6366f1', color: '#fff', fontSize: 10, fontWeight: 700, padding: '1px 6px', borderRadius: 10, marginLeft: 2 }}>ON</span>
                            )}
                        </button>
                    </div>

                    {/* Advanced section */}
                    {showAdvanced && (
                        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '14px 16px', marginBottom: 8 }}>
                            <div className="row g-2 mb-2">
                                {/* Course */}
                                <div className="col-md-3 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>COURSE</label>
                                    <select className="form-select form-select-sm" value={form.course_id} onChange={e => set('course_id', e.target.value)}>
                                        <option value="">All Courses</option>
                                        {courses.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                </div>
                                {/* Academic Year */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>ACADEMIC YEAR</label>
                                    <select className="form-select form-select-sm" value={form.academic_year_id} onChange={e => set('academic_year_id', e.target.value)}>
                                        <option value="">All Years</option>
                                        {academicYears.map(y => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </select>
                                </div>
                                {/* Quota */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>QUOTA</label>
                                    <select className="form-select form-select-sm" value={form.quota} onChange={e => set('quota', e.target.value)}>
                                        <option value="">All Quotas</option>
                                        <option value="management">Management</option>
                                        <option value="counselling">Counselling</option>
                                    </select>
                                </div>
                                {/* Source */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>SOURCE</label>
                                    <select className="form-select form-select-sm" value={form.source} onChange={e => set('source', e.target.value)}>
                                        <option value="">All Sources</option>
                                        {sources.map(s => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                </div>
                                {/* Gender */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>GENDER</label>
                                    <select className="form-select form-select-sm" value={form.gender} onChange={e => set('gender', e.target.value)}>
                                        <option value="">All Genders</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div className="row g-2 mb-2">
                                {/* State */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>STATE</label>
                                    <input className="form-control form-control-sm" type="text" placeholder="e.g. Tamil Nadu"
                                        value={form.state} onChange={e => set('state', e.target.value)} />
                                </div>
                                {/* City */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>CITY</label>
                                    <input className="form-control form-control-sm" type="text" placeholder="e.g. Chennai"
                                        value={form.city} onChange={e => set('city', e.target.value)} />
                                </div>
                                {/* District */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>DISTRICT</label>
                                    <input className="form-control form-control-sm" type="text" placeholder="e.g. Coimbatore"
                                        value={form.district} onChange={e => set('district', e.target.value)} />
                                </div>
                                {/* Follow-up */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>FOLLOW-UP</label>
                                    <select className="form-select form-select-sm" value={form.followup} onChange={e => set('followup', e.target.value)}>
                                        <option value="">Any</option>
                                        <option value="today">Due Today</option>
                                        <option value="overdue">Overdue</option>
                                        <option value="this_week">This Week</option>
                                        <option value="none">No Follow-up Set</option>
                                    </select>
                                </div>
                                {/* No activity */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>NO ACTIVITY (DAYS)</label>
                                    <input className="form-control form-control-sm" type="number" min="1" max="365"
                                        placeholder="e.g. 7" value={form.no_activity_days}
                                        onChange={e => set('no_activity_days', e.target.value)} />
                                </div>
                            </div>

                            <div className="row g-2">
                                {/* SLA */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>SLA STATUS</label>
                                    <select className="form-select form-select-sm" value={form.sla} onChange={e => set('sla', e.target.value)}>
                                        <option value="">Any</option>
                                        <option value="escalated">Escalated</option>
                                        <option value="1">Level 1+</option>
                                        <option value="2">Level 2+</option>
                                    </select>
                                </div>
                                {/* Is Duplicate */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>DUPLICATE</label>
                                    <select className="form-select form-select-sm" value={form.is_duplicate} onChange={e => set('is_duplicate', e.target.value)}>
                                        <option value="">All</option>
                                        <option value="1">Duplicates Only</option>
                                        <option value="0">Non-Duplicates</option>
                                    </select>
                                </div>
                                {/* Is Active */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>ACTIVE STATUS</label>
                                    <select className="form-select form-select-sm" value={form.is_active} onChange={e => set('is_active', e.target.value)}>
                                        <option value="">All</option>
                                        <option value="1">Active Only</option>
                                        <option value="0">Inactive Only</option>
                                    </select>
                                </div>
                                {/* Aged min */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>MIN AGE (DAYS)</label>
                                    <input className="form-control form-control-sm" type="number" min="0" max="3650"
                                        placeholder="e.g. 7" value={form.aged_min}
                                        onChange={e => set('aged_min', e.target.value)} />
                                </div>
                                {/* Aged max */}
                                <div className="col-md-2 col-6">
                                    <label className="form-label form-label-sm mb-1" style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>MAX AGE (DAYS)</label>
                                    <input className="form-control form-control-sm" type="number" min="0" max="3650"
                                        placeholder="e.g. 30" value={form.aged_max}
                                        onChange={e => set('aged_max', e.target.value)} />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="mt-2 d-flex gap-2 flex-wrap align-items-center">
                        <button type="submit" className="btn btn-primary btn-sm px-4 d-flex align-items-center gap-1">
                            <span className="material-icons" style={{ fontSize: 15 }}>filter_list</span>
                            Apply Filters
                        </button>
                        <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={reset}>
                            Reset
                        </button>
                        {activeCount > 0 && (
                            <small className="text-muted">{leads.total} result{leads.total !== 1 ? 's' : ''} match your filters</small>
                        )}
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
                            {leadsState.data.length === 0 ? (
                                <tr><td colSpan={10} className="text-center py-4 text-muted">No Leads Found</td></tr>
                            ) : leadsState.data.map((lead, idx) => {
                                const sno = (leadsState.current_page - 1) * leadsState.per_page + idx + 1;
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
                                        <td>
                                            <StatusBadge status={lead.status} />
                                            {lead.is_active
                                                ? <span className="badge d-block mt-1" style={{ background: '#dcfce7', color: '#16a34a', fontSize: 10, fontWeight: 600 }}>Active</span>
                                                : <span className="badge d-block mt-1" style={{ background: '#fee2e2', color: '#dc2626', fontSize: 10, fontWeight: 600 }}>Inactive</span>
                                            }
                                            {(lead.sla_escalated || lead.sla_level > 0) && (
                                                <div className="mt-1">
                                                    <SlaLevelBadge level={lead.sla_level} escalated={lead.sla_escalated} />
                                                </div>
                                            )}
                                        </td>
                                        <td>{lead.assigned_user || '—'}</td>
                                        <td>{fmtDate(lead.next_followup)}</td>
                                        <td>
                                            <div className="d-flex gap-1 flex-wrap">
                                                <Link href={`/manager/leads/${lead.encrypted_id}`}
                                                    className="btn btn-sm btn-outline-primary">View</Link>
                                                <button type="button" className="btn btn-sm btn-outline-secondary"
                                                    onClick={() => setEditTarget(lead)}
                                                    title="Edit mobile/email">
                                                    <span className="material-icons" style={{ fontSize: 14, verticalAlign: -2 }}>edit</span>
                                                </button>
                                                <button type="button"
                                                    className={`btn btn-sm ${lead.is_active ? 'btn-outline-danger' : 'btn-outline-success'}`}
                                                    title={lead.is_active ? 'Deactivate lead' : 'Activate lead'}
                                                    onClick={async () => {
                                                        if (!confirm(`${lead.is_active ? 'Deactivate' : 'Activate'} this lead?`)) return;
                                                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                                                        const res = await fetch(lead.urls?.toggle_active, {
                                                            method: 'POST',
                                                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                                                        });
                                                        if (res.ok) {
                                                            setLeadsState(prev => ({
                                                                ...prev,
                                                                data: prev.data.map(l => l.id === lead.id ? { ...l, is_active: !l.is_active } : l),
                                                            }));
                                                        }
                                                    }}>
                                                    <span className="material-icons" style={{ fontSize: 14, verticalAlign: -2 }}>
                                                        {lead.is_active ? 'toggle_off' : 'toggle_on'}
                                                    </span>
                                                </button>
                                            </div>
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

            {editTarget && (
                <EditContactModal
                    lead={editTarget}
                    urls={editTarget.urls}
                    onSaved={({ phone, email }) => {
                        setLeadsState(prev => ({
                            ...prev,
                            data: prev.data.map(l => l.id === editTarget.id ? { ...l, phone, email } : l),
                        }));
                    }}
                    onClose={() => setEditTarget(null)}
                />
            )}
        </>
    );
}
