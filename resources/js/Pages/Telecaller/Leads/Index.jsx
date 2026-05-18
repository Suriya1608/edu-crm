import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ─── Design tokens ────────────────────────────────────────────────────────────
const ORG='#FF5C1A', ORGL='#FF8042', DARKC='#1A1A2E';
const BORDER='#EAEAED', TEXT='#1A1A2E', MUTED='#9EA3B0';
const WHITE='#FFFFFF', BGGRAY='#F5F5F7';
const card=(e={})=>({background:WHITE,borderRadius:14,boxShadow:'0 2px 16px rgba(0,0,0,0.07)',border:`1px solid ${BORDER}`,...e});

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
            fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20 }}>
            {days}d old
        </span>
    );
    if (days >= 3) return (
        <span style={{ background: '#fffbeb', color: '#d97706', border: '1px solid #fde68a',
            fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20 }}>
            {days}d old
        </span>
    );
    // 0-2 days → hot / fresh lead
    return (
        <span style={{ background: '#f0fdf4', color: '#16a34a', border: '1px solid #bbf7d0',
            fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
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
const STATUS_COLORS = {
    new:           { bg: '#EEF2FF', color: '#4338CA' },
    assigned:      { bg: '#F0F9FF', color: '#0369A1' },
    contacted:     { bg: '#F0FDF4', color: '#166534' },
    interested:    { bg: '#FFF7ED', color: '#C2410C' },
    follow_up:     { bg: '#FEFCE8', color: '#854D0E' },
    not_interested:{ bg: '#FFF1F2', color: '#9F1239' },
    converted:     { bg: '#F0FDF4', color: '#14532D' },
    lost:          { bg: '#F9FAFB', color: '#374151' },
};
function StatusBadge({ status }) {
    const sc = STATUS_COLORS[status] ?? { bg: '#F3F4F6', color: '#374151' };
    return (
        <span style={{
            background: sc.bg, color: sc.color,
            borderRadius: 20, fontSize: 11, fontWeight: 700, padding: '3px 10px',
            display: 'inline-block', whiteSpace: 'nowrap',
        }}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

// ─── Follow-up date cell ──────────────────────────────────────────────────────
function FollowupCell({ dateStr }) {
    if (!dateStr) return <span style={{ color: MUTED, fontSize: 13 }}>—</span>;
    const due   = new Date(dateStr);
    const today = new Date();
    due.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    const diff  = Math.round((due - today) / 86400000);
    const label = due.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    if (diff < 0) return (
        <div>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#dc2626', background: '#fef2f2',
                border: '1px solid #fecaca', padding: '3px 10px', borderRadius: 20,
                display: 'inline-flex', alignItems: 'center', gap: 3 }}>
                <span className="material-icons" style={{ fontSize: 12 }}>schedule</span>{label}
            </span>
            <div style={{ fontSize: 10.5, color: '#ef4444', fontWeight: 600, marginTop: 2 }}>Overdue</div>
        </div>
    );
    if (diff === 0) return (
        <div>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#d97706', background: '#fffbeb',
                border: '1px solid #fde68a', padding: '3px 10px', borderRadius: 20,
                display: 'inline-flex', alignItems: 'center', gap: 3 }}>
                <span className="material-icons" style={{ fontSize: 12 }}>today</span>{label}
            </span>
            <div style={{ fontSize: 10.5, color: '#d97706', fontWeight: 600, marginTop: 2 }}>Today</div>
        </div>
    );
    return <span style={{ fontSize: 13, color: TEXT }}>{label}</span>;
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
    if (!isoStr) return <span style={{ color: MUTED, fontSize: 12 }}>—</span>;
    const meta = ACTIVITY_META[type] ?? { icon: 'history', color: '#94a3b8', label: type ?? 'Activity' };
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span className="material-icons"
                style={{ fontSize: 15, color: meta.color, flexShrink: 0 }}>
                {meta.icon}
            </span>
            <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: TEXT, lineHeight: 1.2 }}>
                    {meta.label}
                </div>
                <div style={{ fontSize: 11, color: MUTED }}>{relativeTime(isoStr)}</div>
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

// ─── Skeleton table rows ──────────────────────────────────────────────────────
function SkeletonRows({ count = 8 }) {
    return Array.from({ length: count }).map((_, i) => (
        <tr key={i}>
            {Array.from({ length: 10 }).map((__, j) => (
                <td key={j} style={{ padding: '12px 16px', borderBottom: `1px solid ${BORDER}` }}>
                    <div style={{
                        height: j === 3 ? 14 : 10, borderRadius: 6,
                        width: j === 3 ? '80%' : j === 5 ? '60%' : '70%',
                        background: 'linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%)',
                        backgroundSize: '800px 100%',
                        animation: 'shimmer 1.4s infinite linear',
                    }} />
                </td>
            ))}
        </tr>
    ));
}

// ─── Empty state ──────────────────────────────────────────────────────────────
function EmptyState() {
    return (
        <tr>
            <td colSpan={10}>
                <div style={{ textAlign: 'center', padding: '52px 0 44px' }}>
                    <span className="material-icons" style={{ fontSize: 48, color: MUTED, display: 'block', marginBottom: 14 }}>
                        person_search
                    </span>
                    <div style={{ fontSize: 14, fontWeight: 600, color: MUTED, marginBottom: 6 }}>
                        No leads found
                    </div>
                    <div style={{ fontSize: 12, color: MUTED, maxWidth: 280, margin: '0 auto' }}>
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
        <th onClick={handleSort} style={{
            cursor: 'pointer', userSelect: 'none',
            padding: '11px 16px', fontSize: 10.5, fontWeight: 700, color: MUTED,
            textTransform: 'uppercase', letterSpacing: '0.5px',
            borderBottom: `1px solid ${BORDER}`,
            ...style,
        }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, whiteSpace: 'nowrap' }}>
                {children}
                <span className="material-icons"
                    style={{ fontSize: 13, color: isActive ? ORG : '#cbd5e1', transition: 'color .15s' }}>
                    {isActive ? (sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}
                </span>
            </span>
        </th>
    );
}

// ─── Stat card config ─────────────────────────────────────────────────────────
const CARDS = [
    { label: 'Total Leads',        icon: 'groups'       },
    { label: 'New Leads',          icon: 'fiber_new'    },
    { label: 'Interested',         icon: 'thumb_up'     },
    { label: 'Follow-up Today',    icon: 'event'        },
    { label: 'Overdue Follow-ups', icon: 'alarm'        },
    { label: 'Converted (Month)',  icon: 'check_circle' },
];

const ADV_KEYS = ['academic_year_id', 'quota', 'gender', 'state', 'city', 'followup', 'last_call_days', 'has_whatsapp'];
function hasAdvancedFilter(form) {
    return ADV_KEYS.some(k => form[k] !== '' && form[k] != null);
}

// ─── Shared input / select style ─────────────────────────────────────────────
const inputStyle = {
    border: `1px solid ${BORDER}`, borderRadius: 10, padding: '9px 14px',
    fontSize: 13, width: '100%', outline: 'none', background: WHITE, color: TEXT,
    fontFamily: 'Work Sans, sans-serif',
};
const selectStyle = {
    ...inputStyle,
    appearance: 'none', WebkitAppearance: 'none',
    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239EA3B0' d='M6 8L0 0h12z'/%3E%3C/svg%3E")`,
    backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', paddingRight: 32,
};

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ stats, leads, filters, courses, sources, academicYears }) {
    const s = stats ?? {};

    const sort    = filters?.sort     ?? '';
    const sortDir = filters?.sort_dir ?? 'desc';

    const [form, setForm] = useState({
        search:           filters?.search           ?? '',
        status:           filters?.status           ?? '',
        date_range:       filters?.date_range       ?? '',
        date_from:        filters?.date_from        ?? '',
        date_to:          filters?.date_to          ?? '',
        course_id:        filters?.course_id        ?? '',
        source:           filters?.source           ?? '',
        academic_year_id: filters?.academic_year_id ?? '',
        quota:            filters?.quota            ?? '',
        gender:           filters?.gender           ?? '',
        state:            filters?.state            ?? '',
        city:             filters?.city             ?? '',
        followup:         filters?.followup         ?? '',
        last_call_days:   filters?.last_call_days   ?? '',
        has_whatsapp:     filters?.has_whatsapp     ?? '',
    });

    const [showAdvanced, setShowAdvanced] = useState(() => hasAdvancedFilter({ ...form }));

    const [selectedIds, setSelectedIds] = useState(new Set());
    const [bulkStatus, setBulkStatus]   = useState('');
    const [bulkLoading, setBulkLoading] = useState(false);
    const [navigating,  setNavigating]  = useState(false);

    useEffect(() => {
        const off1 = router.on('start',  () => setNavigating(true));
        const off2 = router.on('finish', () => setNavigating(false));
        return () => { off1(); off2(); };
    }, []);

    function buildParams(overrides = {}) {
        const base = {};
        const keys = ['search', 'status', 'date_range', 'date_from', 'date_to',
            'course_id', 'source', 'academic_year_id', 'quota', 'gender',
            'state', 'city', 'followup', 'last_call_days', 'has_whatsapp'];
        keys.forEach(k => { if (form[k]) base[k] = form[k]; });
        if (sort)              base.sort      = sort;
        if (sortDir)           base.sort_dir  = sortDir;
        if (filters?.per_page) base.per_page  = filters.per_page;
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
        setForm({
            search: '', status: '', date_range: '', date_from: '', date_to: '',
            course_id: '', source: '', academic_year_id: '', quota: '', gender: '',
            state: '', city: '', followup: '', last_call_days: '', has_whatsapp: '',
        });
        setShowAdvanced(false);
        router.get('/telecaller/leads', {}, { preserveState: false });
    }

    function changePerPage(value) {
        router.get('/telecaller/leads', buildParams({ per_page: value }), { preserveState: false });
    }

    const statValues = [s.total, s.new, s.interested, s.followup, s.overdue, s.converted_month];
    const activeFilterCount = Object.entries(form).filter(([k, v]) => !['sort','sort_dir','per_page'].includes(k) && v !== '').length;

    return (
        <>
            <Head title="My Leads" />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                @keyframes shimmer {
                    0% { background-position: -800px 0; }
                    100% { background-position: 800px 0; }
                }
                .tl-input:focus { border-color: ${ORG} !important; box-shadow: 0 0 0 3px rgba(255,92,26,0.1) !important; }
                .tl-view-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:10; font-size:13px; font-weight:600; border:none; cursor:pointer; transition:all .2s; text-decoration:none; }
                .tl-view-btn-active { background:${ORG}; color:${WHITE}; }
                .tl-view-btn-inactive { background:${WHITE}; color:${TEXT}; border:1px solid ${BORDER}; }
                .tl-view-btn-inactive:hover { background:${BGGRAY}; }
                .tl-export-dropdown { position:relative; display:inline-block; }
                .tl-export-menu { display:none; position:absolute; right:0; top:calc(100% + 6px); background:${WHITE}; border:1px solid ${BORDER}; borderRadius:10; boxShadow:0 4px 20px rgba(0,0,0,0.1); min-width:180px; z-index:100; overflow:hidden; }
                .tl-export-dropdown:hover .tl-export-menu,
                .tl-export-dropdown:focus-within .tl-export-menu { display:block; }
                .tl-export-item { display:flex; align-items:center; gap:8px; padding:10px 16px; font-size:13px; color:${TEXT}; text-decoration:none; transition:background .15s; }
                .tl-export-item:hover { background:${BGGRAY}; }
                .tl-adv-label { font-size:11px; color:${MUTED}; font-weight:600; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.4px; }
                .tl-page-btn { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px; border-radius:8px; font-size:13px; font-weight:600; border:1px solid ${BORDER}; background:${WHITE}; color:${TEXT}; cursor:pointer; text-decoration:none; transition:all .15s; }
                .tl-page-btn:hover { background:${BGGRAY}; }
                .tl-page-btn-active { background:${ORG} !important; color:${WHITE} !important; border-color:${ORG} !important; }
                .tl-page-btn-disabled { opacity:0.45; pointer-events:none; }
            `}</style>

            {/* ── Page header ─────────────────────────────────────────────────── */}
            <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', flexWrap:'wrap', gap:12, marginBottom:24 }}>
                <div>
                    <h1 style={{ fontSize:22, fontWeight:800, color:TEXT, margin:0, lineHeight:1.2 }}>My Leads</h1>
                    <p style={{ fontSize:13, color:MUTED, margin:'4px 0 0', fontWeight:400 }}>
                        Manage and track your assigned leads
                    </p>
                </div>
                {/* ── View toggle ──────────────────────────────────────────────── */}
                <div style={{ display:'flex', gap:6 }}>
                    <Link href="/telecaller/leads"
                        className="tl-view-btn tl-view-btn-active">
                        <span className="material-icons" style={{ fontSize:15 }}>view_list</span>
                        List
                    </Link>
                    <Link href="/telecaller/leads/pipeline"
                        className="tl-view-btn tl-view-btn-inactive">
                        <span className="material-icons" style={{ fontSize:15 }}>view_kanban</span>
                        Pipeline
                    </Link>
                </div>
            </div>

            {/* ── Stat cards ──────────────────────────────────────────────────── */}
            <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fit, minmax(140px, 1fr))', gap:14, marginBottom:22 }}>
                {CARDS.map((c, i) => (
                    <div key={c.label} style={{ ...card(), padding:'16px 20px', display:'flex', flexDirection:'column', gap:8 }}>
                        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                            <span style={{ fontSize:11, fontWeight:600, color:MUTED, textTransform:'uppercase', letterSpacing:'0.5px' }}>
                                {c.label}
                            </span>
                            <span className="material-icons" style={{ fontSize:18, color:ORG, opacity:0.8 }}>{c.icon}</span>
                        </div>
                        <div style={{ fontSize:28, fontWeight:800, color:ORG, lineHeight:1 }}>
                            {statValues[i] ?? '—'}
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Filter panel ─────────────────────────────────────────────────── */}
            <div style={{ ...card(), padding:'16px 20px', marginBottom:18 }}>
                <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', marginBottom:14 }}>
                    <div>
                        <div style={{ fontSize:14, fontWeight:700, color:TEXT }}>Filter Leads</div>
                        <div style={{ fontSize:12, color:MUTED }}>Refine by date, status, and lead details</div>
                    </div>
                    {activeFilterCount > 0 && (
                        <span style={{ background:ORG, color:WHITE, fontSize:12, fontWeight:600, padding:'3px 10px', borderRadius:20 }}>
                            {activeFilterCount} active
                        </span>
                    )}
                </div>
                <form onSubmit={handleFilter}>
                    {/* Basic row */}
                    <div style={{ display:'grid', gridTemplateColumns:'2fr 1fr 1fr 1fr 1fr', gap:10, marginBottom:10 }}>
                        <input className="tl-input" style={inputStyle} type="text"
                            placeholder="Search by name, phone or lead code…"
                            value={form.search}
                            onChange={e => setForm({ ...form, search: e.target.value })} />
                        <select className="tl-input" style={selectStyle} value={form.status}
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
                        <select className="tl-input" style={selectStyle} value={form.date_range}
                            onChange={e => setForm({ ...form, date_range: e.target.value })}>
                            <option value="">Any Date</option>
                            <option value="today">Today</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <select className="tl-input" style={selectStyle} value={form.course_id}
                            onChange={e => setForm({ ...form, course_id: e.target.value })}>
                            <option value="">All Courses</option>
                            {(courses ?? []).map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                        <select className="tl-input" style={selectStyle} value={form.source}
                            onChange={e => setForm({ ...form, source: e.target.value })}>
                            <option value="">All Sources</option>
                            {(sources ?? []).map(s => (
                                <option key={s} value={s}>{s}</option>
                            ))}
                        </select>
                    </div>

                    {/* Custom date range inputs */}
                    {form.date_range === 'custom' && (
                        <div style={{ display:'flex', gap:10, marginBottom:10 }}>
                            <input type="date" className="tl-input" style={{ ...inputStyle, width:'auto' }}
                                value={form.date_from} title="From date"
                                onChange={e => setForm({ ...form, date_from: e.target.value })} />
                            <input type="date" className="tl-input" style={{ ...inputStyle, width:'auto' }}
                                value={form.date_to} title="To date"
                                onChange={e => setForm({ ...form, date_to: e.target.value })} />
                        </div>
                    )}

                    {/* Advanced toggle */}
                    <div style={{ marginBottom:10 }}>
                        <button type="button"
                            style={{
                                display:'inline-flex', alignItems:'center', gap:6, padding:'7px 14px',
                                borderRadius:10, fontSize:12, fontWeight:600, cursor:'pointer', border:'none',
                                background: showAdvanced ? `rgba(255,92,26,0.08)` : BGGRAY,
                                color: showAdvanced ? ORG : MUTED,
                                transition:'all .2s',
                            }}
                            onClick={() => setShowAdvanced(v => !v)}>
                            <span className="material-icons" style={{ fontSize:14 }}>
                                {showAdvanced ? 'expand_less' : 'tune'}
                            </span>
                            {showAdvanced ? 'Hide Advanced Filters' : 'Advanced Filters'}
                            {hasAdvancedFilter(form) && (
                                <span style={{ background:ORG, color:WHITE, fontSize:10, fontWeight:700, padding:'1px 7px', borderRadius:10, marginLeft:2 }}>ON</span>
                            )}
                        </button>
                    </div>

                    {/* Advanced section */}
                    {showAdvanced && (
                        <div style={{ background:BGGRAY, border:`1px solid ${BORDER}`, borderRadius:10, padding:'14px 16px', marginBottom:10 }}>
                            <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(160px, 1fr))', gap:10, marginBottom:10 }}>
                                <div>
                                    <label className="tl-adv-label">Academic Year</label>
                                    <select className="tl-input" style={selectStyle} value={form.academic_year_id}
                                        onChange={e => setForm({ ...form, academic_year_id: e.target.value })}>
                                        <option value="">All Years</option>
                                        {(academicYears ?? []).map(y => (
                                            <option key={y.id} value={y.id}>{y.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="tl-adv-label">Quota</label>
                                    <select className="tl-input" style={selectStyle} value={form.quota}
                                        onChange={e => setForm({ ...form, quota: e.target.value })}>
                                        <option value="">All Quotas</option>
                                        <option value="management">Management</option>
                                        <option value="counselling">Counselling</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="tl-adv-label">Gender</label>
                                    <select className="tl-input" style={selectStyle} value={form.gender}
                                        onChange={e => setForm({ ...form, gender: e.target.value })}>
                                        <option value="">All Genders</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="tl-adv-label">State</label>
                                    <input className="tl-input" style={inputStyle} type="text"
                                        placeholder="e.g. Tamil Nadu" value={form.state}
                                        onChange={e => setForm({ ...form, state: e.target.value })} />
                                </div>
                                <div>
                                    <label className="tl-adv-label">City</label>
                                    <input className="tl-input" style={inputStyle} type="text"
                                        placeholder="e.g. Chennai" value={form.city}
                                        onChange={e => setForm({ ...form, city: e.target.value })} />
                                </div>
                            </div>
                            <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(180px, 1fr))', gap:10 }}>
                                <div>
                                    <label className="tl-adv-label">Follow-up</label>
                                    <select className="tl-input" style={selectStyle} value={form.followup}
                                        onChange={e => setForm({ ...form, followup: e.target.value })}>
                                        <option value="">Any</option>
                                        <option value="today">Due Today</option>
                                        <option value="overdue">Overdue</option>
                                        <option value="this_week">This Week</option>
                                        <option value="none">No Follow-up Set</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="tl-adv-label">Not Called In (Days)</label>
                                    <input className="tl-input" style={inputStyle} type="number"
                                        min="1" max="365" placeholder="e.g. 7"
                                        value={form.last_call_days}
                                        onChange={e => setForm({ ...form, last_call_days: e.target.value })} />
                                </div>
                                <div>
                                    <label className="tl-adv-label">WhatsApp</label>
                                    <select className="tl-input" style={selectStyle} value={form.has_whatsapp}
                                        onChange={e => setForm({ ...form, has_whatsapp: e.target.value })}>
                                        <option value="">All Leads</option>
                                        <option value="1">Has WhatsApp Conversation</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    )}

                    <div style={{ display:'flex', gap:8, flexWrap:'wrap', alignItems:'center', marginTop:6 }}>
                        <button type="submit" style={{
                            display:'inline-flex', alignItems:'center', gap:6,
                            background:ORG, color:WHITE, border:'none', borderRadius:10,
                            padding:'8px 18px', fontSize:13, fontWeight:600, cursor:'pointer',
                            transition:'background .2s',
                        }}>
                            <span className="material-icons" style={{ fontSize:15 }}>filter_list</span>
                            Apply Filters
                        </button>
                        <button type="button" onClick={resetFilter} style={{
                            display:'inline-flex', alignItems:'center', gap:6,
                            background:BGGRAY, color:MUTED, border:`1px solid ${BORDER}`, borderRadius:10,
                            padding:'8px 18px', fontSize:13, fontWeight:600, cursor:'pointer',
                        }}>
                            Reset
                        </button>
                        <div className="tl-export-dropdown" style={{ marginLeft:'auto' }}>
                            <button type="button" style={{
                                display:'inline-flex', alignItems:'center', gap:6,
                                background:BGGRAY, color:TEXT, border:`1px solid ${BORDER}`, borderRadius:10,
                                padding:'8px 18px', fontSize:13, fontWeight:600, cursor:'pointer',
                            }}>
                                <span className="material-icons" style={{ fontSize:15, color:'#10b981' }}>download</span>
                                Export
                                <span className="material-icons" style={{ fontSize:14, color:MUTED }}>expand_more</span>
                            </button>
                            <div className="tl-export-menu">
                                <a className="tl-export-item" href={exportUrl('excel')} target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize:16, color:'#10b981' }}>table_view</span>
                                    Excel (.xlsx)
                                </a>
                                <a className="tl-export-item" href={exportUrl('pdf')} target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize:16, color:'#ef4444' }}>picture_as_pdf</span>
                                    PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {/* ── Leads table ───────────────────────────────────────────────── */}
            <div style={{ ...card(), overflow:'hidden' }}>
                {/* Table header bar */}
                <div style={{ padding:'16px 20px', display:'flex', alignItems:'center', justifyContent:'space-between', borderBottom:`1px solid ${BORDER}` }}>
                    <div>
                        <span style={{ fontSize:15, fontWeight:700, color:TEXT }}>My Lead List</span>
                        <span style={{ fontSize:12, color:MUTED, marginLeft:10 }}>{leads.total} records</span>
                    </div>
                </div>

                {/* ── Bulk action bar ───────────────────────────────────────────── */}
                {selectedIds.size > 0 && (
                    <div style={{ background:`rgba(255,92,26,0.06)`, borderBottom:`1px solid rgba(255,92,26,0.18)`,
                        padding:'10px 20px', display:'flex', alignItems:'center', gap:12, flexWrap:'wrap' }}>
                        <span style={{ fontSize:13, fontWeight:600, color:ORG }}>
                            {selectedIds.size} lead{selectedIds.size > 1 ? 's' : ''} selected
                        </span>
                        <select style={{ ...selectStyle, width:200, padding:'7px 32px 7px 12px' }}
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
                        <button type="button"
                            disabled={!bulkStatus || bulkLoading}
                            onClick={applyBulkStatus}
                            style={{
                                background: (!bulkStatus || bulkLoading) ? '#e5e7eb' : ORG,
                                color: (!bulkStatus || bulkLoading) ? MUTED : WHITE,
                                border:'none', borderRadius:10, padding:'8px 18px',
                                fontSize:13, fontWeight:600, cursor: (!bulkStatus || bulkLoading) ? 'not-allowed' : 'pointer',
                            }}>
                            {bulkLoading ? 'Updating…' : 'Apply'}
                        </button>
                        <button type="button"
                            onClick={() => setSelectedIds(new Set())}
                            style={{
                                background:BGGRAY, color:MUTED, border:`1px solid ${BORDER}`,
                                borderRadius:10, padding:'8px 14px', fontSize:13, fontWeight:600, cursor:'pointer',
                            }}>
                            Clear
                        </button>
                    </div>
                )}

                <div style={{ overflowX:'auto' }}>
                    <table style={{ width:'100%', borderCollapse:'collapse', minWidth:600 }}>
                        <thead>
                            <tr style={{ position:'sticky', top:0, background:'#F8F9FB', zIndex:2 }}>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}`,
                                    width:36 }}>
                                    <input type="checkbox"
                                        checked={leads.data.length > 0 && selectedIds.size === leads.data.length}
                                        onChange={toggleSelectAll}
                                        title="Select all on this page" />
                                </th>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}`,
                                    width:44 }}>S.No</th>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Lead Code</th>
                                <SortHeader field="name" sort={sort} sortDir={sortDir}
                                    allFilters={buildParams()}>Name</SortHeader>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Phone</th>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Course</th>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Status</th>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Last Activity</th>
                                <SortHeader field="next_followup" sort={sort} sortDir={sortDir}
                                    allFilters={buildParams()}>Next Follow-up</SortHeader>
                                <th style={{ padding:'11px 16px', fontSize:10.5, fontWeight:700, color:MUTED,
                                    textTransform:'uppercase', letterSpacing:'0.5px', borderBottom:`1px solid ${BORDER}` }}>
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {navigating ? <SkeletonRows count={leads.data.length || 8} /> :
                            leads.data.length === 0 ? <EmptyState /> : leads.data.map((lead, idx) => {
                                const sno  = (leads.current_page - 1) * leads.per_page + idx + 1;
                                const href = `/telecaller/leads/${lead.encrypted_id}`;
                                const isSelected = selectedIds.has(lead.id);
                                return (
                                    <tr key={lead.id}
                                        onClick={() => router.visit(href)}
                                        style={{ cursor:'pointer', background: isSelected ? `rgba(255,92,26,0.06)` : WHITE, transition:'background .15s' }}
                                        onMouseEnter={e => { if (!isSelected) e.currentTarget.style.background = '#FAFAFA'; }}
                                        onMouseLeave={e => { e.currentTarget.style.background = isSelected ? `rgba(255,92,26,0.06)` : WHITE; }}>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}
                                            onClick={e => e.stopPropagation()}>
                                            <input type="checkbox" checked={isSelected}
                                                onChange={() => toggleSelect(lead.id)} />
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:MUTED, borderBottom:`1px solid ${BORDER}` }}>
                                            {sno}
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <span style={{ fontFamily:'monospace', fontSize:12,
                                                background:BGGRAY, padding:'2px 8px', borderRadius:6,
                                                color:TEXT, border:`1px solid ${BORDER}` }}>
                                                {lead.lead_code}
                                            </span>
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <div style={{ fontWeight:600, display:'flex', alignItems:'center', gap:6, flexWrap:'wrap', marginBottom:2 }}>
                                                {lead.name}
                                                <AgingBadge days={lead.days_aged} />
                                            </div>
                                            <div style={{ fontSize:11, color:MUTED }}>{lead.email || '—'}</div>
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <span style={{ fontWeight:600, color:TEXT }}>
                                                {lead.phone || '—'}
                                            </span>
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            {lead.course || '—'}
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <StatusBadge status={lead.status} />
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <LastActivityCell
                                                type={lead.last_activity_type}
                                                isoStr={lead.last_activity_at} />
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}>
                                            <FollowupCell dateStr={lead.next_followup} />
                                        </td>
                                        <td style={{ padding:'12px 16px', fontSize:13, color:TEXT, borderBottom:`1px solid ${BORDER}` }}
                                            onClick={e => e.stopPropagation()}>
                                            <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                                                <QuickCallBtn phone={lead.phone} leadId={lead.id} />
                                                <Link href={href} style={{
                                                    display:'inline-flex', alignItems:'center', padding:'5px 12px',
                                                    borderRadius:8, fontSize:12, fontWeight:600,
                                                    background:BGGRAY, color:TEXT, border:`1px solid ${BORDER}`,
                                                    textDecoration:'none', transition:'background .15s',
                                                }}>
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

                {/* ── Pagination ─────────────────────────────────────────────── */}
                <div style={{ padding:'14px 20px', display:'flex', justifyContent:'space-between', alignItems:'center', flexWrap:'wrap', gap:12, borderTop:`1px solid ${BORDER}` }}>
                    <div style={{ display:'flex', alignItems:'center', gap:14, flexWrap:'wrap' }}>
                        <span style={{ fontSize:12, color:MUTED }}>
                            Showing {leads.from ?? 0}–{leads.to ?? 0} of {leads.total} results
                        </span>
                        <div style={{ display:'flex', alignItems:'center', gap:6 }}>
                            <span style={{ fontSize:12, color:MUTED }}>Per page:</span>
                            <select style={{ ...selectStyle, width:72, padding:'6px 28px 6px 10px', fontSize:12 }}
                                value={filters?.per_page || '15'}
                                onChange={e => changePerPage(e.target.value)}>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                    {leads.last_page > 1 && (
                        <div style={{ display:'flex', gap:4, flexWrap:'wrap' }}>
                            {leads.links.map((link, i) => {
                                const isActive   = link.active;
                                const isDisabled = !link.url;
                                return link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`tl-page-btn${isActive ? ' tl-page-btn-active' : ''}${isDisabled ? ' tl-page-btn-disabled' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span key={i}
                                        className={`tl-page-btn tl-page-btn-disabled`}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
