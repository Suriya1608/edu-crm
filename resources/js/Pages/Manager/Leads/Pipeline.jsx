import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

const STATUS_CONFIG = {
    new:            { label: 'New',           color: '#137fec', bg: '#eff6ff', icon: 'fiber_new' },
    assigned:       { label: 'Assigned',       color: '#8b5cf6', bg: '#f5f3ff', icon: 'assignment_ind' },
    contacted:      { label: 'Contacted',      color: '#f59e0b', bg: '#fffbeb', icon: 'phone_in_talk' },
    interested:     { label: 'Interested',     color: '#10b981', bg: '#ecfdf5', icon: 'thumb_up' },
    follow_up:      { label: 'Follow Up',      color: '#f97316', bg: '#fff7ed', icon: 'event_repeat' },
    not_interested: { label: 'Not Interested', color: '#ef4444', bg: '#fef2f2', icon: 'thumb_down' },
    converted:      { label: 'Converted',      color: '#059669', bg: '#d1fae5', icon: 'check_circle' },
};
const STATUSES = Object.keys(STATUS_CONFIG);

function AgingBadge({ days }) {
    if (days >= 6) return <span style={{ fontSize: 10, fontWeight: 600, padding: '1px 5px', borderRadius: 4, background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca' }}>{days}d</span>;
    if (days >= 3) return <span style={{ fontSize: 10, fontWeight: 600, padding: '1px 5px', borderRadius: 4, background: '#fffbeb', color: '#d97706', border: '1px solid #fde68a' }}>{days}d</span>;
    return null;
}

function fmtDate(d) {
    if (!d) return null;
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ─── Single Kanban card ───────────────────────────────────────────────────────
function KanbanCard({ lead }) {
    return (
        <div className="kanban-card" data-id={lead.encrypted_id}
            style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: 12, cursor: 'grab', transition: 'box-shadow .15s, transform .15s', position: 'relative' }}>
            <div className="d-flex justify-content-between align-items-center mb-1">
                <span style={{ fontSize: 10, fontWeight: 700, color: '#64748b', letterSpacing: '.4px' }}>{lead.lead_code}</span>
                <AgingBadge days={lead.days_aged} />
            </div>
            <div style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', lineHeight: 1.3, marginBottom: 6 }}>
                {lead.name}
                {lead.is_duplicate && (
                    <span style={{ fontSize: 9, background: '#fff7ed', color: '#ea580c', border: '1px solid #fed7aa', padding: '1px 5px', borderRadius: 4, fontWeight: 600, verticalAlign: 'middle', marginLeft: 4 }}>DUP</span>
                )}
            </div>
            <div className="d-flex align-items-center gap-1 mb-1" style={{ fontSize: 12, color: '#475569' }}>
                <span className="material-icons" style={{ fontSize: 13, color: '#94a3b8' }}>phone</span>
                {lead.phone}
            </div>
            {lead.course && (
                <div className="d-flex align-items-center gap-1 mb-1" style={{ fontSize: 11, color: '#64748b' }}>
                    <span className="material-icons" style={{ fontSize: 13, color: '#94a3b8' }}>school</span>
                    {lead.course}
                </div>
            )}
            <div className="d-flex align-items-center gap-1 mb-2" style={{ fontSize: 11, color: '#64748b' }}>
                <span className="material-icons" style={{ fontSize: 13, color: '#94a3b8' }}>person</span>
                <span data-assigned>{lead.assigned_user ?? 'Unassigned'}</span>
            </div>
            {lead.next_followup && (
                <div className="d-flex align-items-center gap-1 mb-2" style={{ fontSize: 11, color: '#f97316' }}>
                    <span className="material-icons" style={{ fontSize: 13 }}>event</span>
                    {fmtDate(lead.next_followup)}
                </div>
            )}
            <div className="d-flex justify-content-between align-items-center" style={{ marginTop: 4, paddingTop: 8, borderTop: '1px solid #f1f5f9' }}>
                <span style={{ fontSize: 10, color: '#94a3b8' }}>{lead.created_at}</span>
                <Link href={`/manager/leads/${lead.encrypted_id}`}
                    style={{ fontSize: 11, padding: '2px 10px', background: '#eff6ff', color: '#137fec', border: '1px solid #bfdbfe', borderRadius: 6, fontWeight: 600, textDecoration: 'none' }}>
                    View
                </Link>
            </div>
        </div>
    );
}

// ─── Kanban column ────────────────────────────────────────────────────────────
function KanbanColumn({ statusKey, cfg, initialLeads, total, filters, urls, onDrop, telecallers }) {
    const [leads,   setLeads]   = useState(initialLeads);
    const [count,   setCount]   = useState(total);
    const [loaded,  setLoaded]  = useState(initialLeads.length);
    const [hasMore, setHasMore] = useState(total > initialLeads.length);
    const [loading, setLoading] = useState(false);
    const bodyRef = useRef(null);

    // expose imperative API for drag-drop parent
    const api = useRef({ leads, setLeads, count, setCount });
    useEffect(() => { api.current = { leads, setLeads, count, setCount }; }, [leads, count]);

    // Register this column's API with parent
    useEffect(() => { onDrop(statusKey, api); }, []);

    async function loadMore() {
        setLoading(true);
        try {
            const params = new URLSearchParams({ status: statusKey, offset: loaded, ...filters });
            const res    = await fetch(`${urls.pipeline_more}?${params}`, { headers: { Accept: 'application/json' } });
            const data   = await res.json();
            setLeads(prev => [...prev, ...data.leads]);
            setLoaded(data.loaded);
            setHasMore(data.has_more);
        } catch (_) {}
        setLoading(false);
    }

    return (
        <div className="kanban-column" data-status={statusKey}
            style={{ minWidth: 272, maxWidth: 272, background: '#fff', borderRadius: 12, border: '1px solid #e2e8f0', display: 'flex', flexDirection: 'column', flexShrink: 0 }}>

            {/* header */}
            <div style={{ padding: '14px 16px 12px', borderBottom: `2px solid ${cfg.color}`, borderRadius: '12px 12px 0 0', background: cfg.bg }}>
                <div className="d-flex align-items-center justify-content-between">
                    <div className="d-flex align-items-center gap-2">
                        <span className="material-icons" style={{ fontSize: 18, color: cfg.color }}>{cfg.icon}</span>
                        <span style={{ fontSize: 13, fontWeight: 700, color: cfg.color }}>{cfg.label}</span>
                    </div>
                    <span id={`col-count-${statusKey}`}
                        style={{ background: cfg.color, color: '#fff', fontSize: 11, fontWeight: 700, padding: '2px 9px', borderRadius: 20, minWidth: 24, textAlign: 'center' }}>
                        {count}
                    </span>
                </div>
            </div>

            {/* cards */}
            <div ref={bodyRef} className="kanban-column-body"
                style={{ padding: 10, overflowY: 'auto', maxHeight: 'calc(100vh - 340px)', minHeight: 80, display: 'flex', flexDirection: 'column', gap: 8 }}>
                {leads.length === 0 && (
                    <div style={{ textAlign: 'center', padding: '24px 12px', color: '#94a3b8', fontSize: 12 }}>
                        <span className="material-icons" style={{ fontSize: 28, display: 'block', marginBottom: 4, opacity: .4 }}>inbox</span>
                        No leads
                    </div>
                )}
                {leads.map(lead => <KanbanCard key={lead.id} lead={lead} />)}
            </div>

            {/* load more */}
            {hasMore && (
                <div style={{ padding: '8px 10px 10px' }}>
                    <div style={{ fontSize: 11, color: '#94a3b8', textAlign: 'center', marginBottom: 6 }}>
                        Showing {loaded} of {count}
                    </div>
                    <button onClick={loadMore} disabled={loading}
                        style={{ width: '100%', border: '1px dashed #cbd5e1', background: 'transparent', color: '#64748b', fontSize: 12, fontWeight: 600, padding: 8, borderRadius: 8, cursor: 'pointer' }}>
                        <span className="material-icons" style={{ fontSize: 14, verticalAlign: 'middle' }}>{loading ? 'refresh' : 'expand_more'}</span>
                        {loading ? ' Loading…' : ' Load More'}
                    </button>
                </div>
            )}
        </div>
    );
}

// ─── Main Pipeline ────────────────────────────────────────────────────────────
export default function Pipeline({ columns, columnTotals, telecallers, filters, urls }) {
    const [form, setForm]           = useState({ search: filters?.search ?? '', telecaller: filters?.telecaller ?? '', date_range: filters?.date_range ?? '' });
    const [toast, setToast]         = useState(null);
    const [pendingDrag, setPending] = useState(null);
    const [overlay, setOverlay]     = useState(false);
    const colApis = useRef({});

    function showToast(msg, type) {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3200);
    }

    function applyFilter(e) {
        e.preventDefault();
        const p = {};
        if (form.search)     p.search     = form.search;
        if (form.telecaller) p.telecaller = form.telecaller;
        if (form.date_range) p.date_range = form.date_range;
        router.get('/manager/leads/pipeline', p, { preserveState: false });
    }

    function resetFilter() {
        setForm({ search: '', telecaller: '', date_range: '' });
        router.get('/manager/leads/pipeline', {}, { preserveState: false });
    }

    // Register column API refs
    function registerColumn(statusKey, apiRef) {
        colApis.current[statusKey] = apiRef;
    }

    // Send status update to backend
    async function sendStatusUpdate(leadEncId, newStatus, oldStatus, telecallerId) {
        setOverlay(true);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const body = { lead_id: leadEncId, status: newStatus };
        if (telecallerId) body.telecaller_id = telecallerId;
        try {
            const res  = await fetch(urls.pipeline_status, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
            const data = await res.json();
            if (data.success) {
                showToast(`Moved to "${newStatus.replace('_', ' ')}"`, 'success');
                if (data.telecaller_name) {
                    // Update assigned name on card in new column
                    const api = colApis.current[newStatus]?.current;
                    if (api) {
                        api.setLeads(prev => prev.map(l => l.encrypted_id === leadEncId ? { ...l, assigned_user: data.telecaller_name } : l));
                    }
                }
            } else {
                // Revert: move card back
                revertMove(leadEncId, newStatus, oldStatus);
                showToast(data.message || 'Failed to update status.', 'error');
            }
        } catch (_) {
            revertMove(leadEncId, newStatus, oldStatus);
            showToast('Network error — status not saved.', 'error');
        }
        setOverlay(false);
    }

    function revertMove(leadEncId, fromStatus, toStatus) {
        const fromApi = colApis.current[fromStatus]?.current;
        const toApi   = colApis.current[toStatus]?.current;
        if (!fromApi || !toApi) return;
        const lead = fromApi.leads.find(l => l.encrypted_id === leadEncId);
        if (!lead) return;
        fromApi.setLeads(prev => prev.filter(l => l.encrypted_id !== leadEncId));
        fromApi.setCount(c => Math.max(0, c - 1));
        toApi.setLeads(prev => [lead, ...prev]);
        toApi.setCount(c => c + 1);
    }

    // SortableJS initialisation
    useEffect(() => {
        let Sortable = window.Sortable;
        function initSortable(S) {
            document.querySelectorAll('.kanban-column-body').forEach(colBody => {
                S.create(colBody, {
                    group: 'leads-pipeline', animation: 160,
                    ghostClass: 'kanban-ghost', dragClass: 'kanban-dragging', handle: '.kanban-card',
                    onEnd(evt) {
                        const card      = evt.item;
                        const newStatus = evt.to.closest('.kanban-column')?.dataset.status;
                        const oldStatus = evt.from.closest('.kanban-column')?.dataset.status;
                        if (!newStatus || !oldStatus || newStatus === oldStatus) return;

                        const leadEncId = card.dataset.id;

                        // Update React state for both columns
                        const fromApi = colApis.current[oldStatus]?.current;
                        const toApi   = colApis.current[newStatus]?.current;
                        if (fromApi && toApi) {
                            const lead = fromApi.leads.find(l => l.encrypted_id === leadEncId);
                            if (lead) {
                                fromApi.setLeads(prev => prev.filter(l => l.encrypted_id !== leadEncId));
                                fromApi.setCount(c => Math.max(0, c - 1));
                                toApi.setLeads(prev => [{ ...lead, status: newStatus }, ...prev]);
                                toApi.setCount(c => c + 1);
                            }
                        }

                        if (newStatus === 'assigned') {
                            setPending({ leadEncId, newStatus, oldStatus });
                            new window.bootstrap.Modal(document.getElementById('assignTelecallerModal')).show();
                        } else {
                            sendStatusUpdate(leadEncId, newStatus, oldStatus, null);
                        }
                    },
                });
            });
        }

        if (Sortable) { initSortable(Sortable); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
        s.onload = () => initSortable(window.Sortable);
        document.head.appendChild(s);
    }, []);

    return (
        <>
            <Head title="Lead Pipeline" />

            {/* ── Toolbar ───────────────────────────────────────────────── */}
            <div className="d-flex align-items-center gap-2 flex-wrap mb-3">
                <Link href="/manager/leads" className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 15 }}>view_list</span>
                    List View
                </Link>
                <Link href="/manager/leads/create" className="btn btn-sm btn-primary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 15 }}>add</span>
                    Add Lead
                </Link>
                <a href="/manager/leads/export" className="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 15 }}>download</span>
                    Export
                </a>
            </div>

            {/* ── Filters ───────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <form onSubmit={applyFilter} className="d-flex flex-wrap gap-3 align-items-end">
                    <div>
                        <label className="form-label mb-1" style={{ fontSize: 12, fontWeight: 600 }}>Date</label>
                        <select className="form-select form-select-sm" style={{ width: 130 }}
                            value={form.date_range} onChange={e => setForm({ ...form, date_range: e.target.value })}>
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                    <div>
                        <label className="form-label mb-1" style={{ fontSize: 12, fontWeight: 600 }}>Telecaller</label>
                        <select className="form-select form-select-sm" style={{ width: 170 }}
                            value={form.telecaller} onChange={e => setForm({ ...form, telecaller: e.target.value })}>
                            <option value="">All Telecallers</option>
                            {telecallers.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="form-label mb-1" style={{ fontSize: 12, fontWeight: 600 }}>Search</label>
                        <input type="text" className="form-control form-control-sm" style={{ width: 200 }}
                            placeholder="Name / Phone / Code"
                            value={form.search} onChange={e => setForm({ ...form, search: e.target.value })} />
                    </div>
                    <div className="d-flex gap-2">
                        <button type="submit" className="btn btn-primary btn-sm px-3">Apply</button>
                        <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={resetFilter}>Reset</button>
                    </div>
                </form>
            </div>

            {/* ── Summary badges ────────────────────────────────────────── */}
            <div className="d-flex flex-wrap gap-2 mb-3">
                {STATUSES.map(key => {
                    const cfg = STATUS_CONFIG[key];
                    return (
                        <span key={key} className="badge d-flex align-items-center gap-1 px-3 py-2"
                            style={{ background: cfg.bg, color: cfg.color, border: `1px solid ${cfg.color}20`, fontSize: 12, fontWeight: 600, borderRadius: 20 }}>
                            <span className="material-icons" style={{ fontSize: 13 }}>{cfg.icon}</span>
                            {cfg.label}
                            <span className="ms-1" id={`badge-count-${key}`}>{columnTotals[key]}</span>
                        </span>
                    );
                })}
            </div>

            {/* ── Board ─────────────────────────────────────────────────── */}
            <div style={{ display: 'flex', gap: 14, overflowX: 'auto', paddingBottom: 20, alignItems: 'flex-start' }}>
                {STATUSES.map(key => (
                    <KanbanColumn key={key} statusKey={key} cfg={STATUS_CONFIG[key]}
                        initialLeads={columns[key] ?? []} total={columnTotals[key] ?? 0}
                        filters={filters} urls={urls}
                        onDrop={registerColumn} telecallers={telecallers} />
                ))}
            </div>

            {/* ── Assign telecaller modal ───────────────────────────────── */}
            <div className="modal fade" id="assignTelecallerModal" tabIndex={-1} data-bs-backdrop="static">
                <div className="modal-dialog modal-dialog-centered" style={{ maxWidth: 420 }}>
                    <div className="modal-content" style={{ borderRadius: 14, border: 'none', boxShadow: '0 20px 60px rgba(0,0,0,.15)' }}>
                        <div className="modal-header" style={{ borderBottom: '1px solid #f1f5f9', padding: '20px 24px 16px' }}>
                            <div className="d-flex align-items-center gap-2">
                                <span className="material-icons" style={{ color: '#8b5cf6', fontSize: 22 }}>assignment_ind</span>
                                <h5 className="modal-title mb-0" style={{ fontSize: 15, fontWeight: 700 }}>Assign Telecaller</h5>
                            </div>
                        </div>
                        <div className="modal-body" style={{ padding: '20px 24px' }}>
                            <p style={{ fontSize: 13, color: '#64748b', marginBottom: 16 }}>
                                Select a telecaller to assign this lead to. The lead status will be set to <strong>Assigned</strong>.
                            </p>
                            <label className="form-label" style={{ fontSize: 12, fontWeight: 600 }}>Telecaller</label>
                            <select id="modalTelecallerSelect" className="form-select">
                                <option value="">-- Select Telecaller --</option>
                                {telecallers.map(t => <option key={t.id} value={t.encrypted_id}>{t.name}</option>)}
                            </select>
                            <div id="assignModalError" style={{ display: 'none', color: '#ef4444', fontSize: 12, marginTop: 8 }}>
                                Please select a telecaller to continue.
                            </div>
                        </div>
                        <div className="modal-footer" style={{ borderTop: '1px solid #f1f5f9', padding: '16px 24px' }}>
                            <button type="button" className="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal"
                                onClick={() => { if (pendingDrag) { revertMove(pendingDrag.leadEncId, pendingDrag.newStatus, pendingDrag.oldStatus); setPending(null); } }}>
                                Cancel
                            </button>
                            <button type="button" className="btn btn-primary btn-sm px-4"
                                onClick={() => {
                                    const sel = document.getElementById('modalTelecallerSelect');
                                    if (!sel.value) { document.getElementById('assignModalError').style.display = 'block'; return; }
                                    document.getElementById('assignModalError').style.display = 'none';
                                    const { leadEncId, newStatus, oldStatus } = pendingDrag;
                                    setPending(null);
                                    window.bootstrap.Modal.getInstance(document.getElementById('assignTelecallerModal')).hide();
                                    sendStatusUpdate(leadEncId, newStatus, oldStatus, sel.value);
                                }}>
                                <span className="material-icons" style={{ fontSize: 14, verticalAlign: 'middle' }}>check</span>
                                Assign &amp; Move
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Overlay ───────────────────────────────────────────────── */}
            {overlay && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(255,255,255,.5)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div className="spinner-border text-primary"></div>
                </div>
            )}

            {/* ── Toast ─────────────────────────────────────────────────── */}
            {toast && (
                <div style={{ position: 'fixed', bottom: 24, right: 24, zIndex: 10000, minWidth: 220 }}>
                    <div style={{ padding: '12px 18px', borderRadius: 10, fontSize: 13, fontWeight: 600, color: '#fff', boxShadow: '0 4px 16px rgba(0,0,0,.15)', background: toast.type === 'success' ? '#10b981' : '#ef4444' }}>
                        {toast.msg}
                    </div>
                </div>
            )}

            <style>{`
                .kanban-ghost    { opacity:.4;background:#eff6ff !important;border:2px dashed #137fec !important; }
                .kanban-dragging { box-shadow:0 12px 32px rgba(0,0,0,.18) !important;transform:rotate(1.5deg) !important;z-index:9999;cursor:grabbing !important; }
                #kanbanBoard::-webkit-scrollbar { height:6px; }
                #kanbanBoard::-webkit-scrollbar-track { background:#f1f5f9;border-radius:4px; }
                #kanbanBoard::-webkit-scrollbar-thumb { background:#cbd5e1;border-radius:4px; }
                .kanban-column-body::-webkit-scrollbar { width:4px; }
                .kanban-column-body::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:4px; }
            `}</style>
        </>
    );
}
