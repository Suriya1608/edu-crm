import { Head, Link, router } from '@inertiajs/react';
import { useState, useRef, useCallback } from 'react';

// ─── Status config ────────────────────────────────────────────────────────────
const STATUS_CONFIG = {
    new:            { label: 'New',            color: '#6366f1', bg: '#eff0ff', icon: 'fiber_new' },
    assigned:       { label: 'Assigned',        color: '#8b5cf6', bg: '#f5f3ff', icon: 'assignment_ind' },
    contacted:      { label: 'Contacted',       color: '#f59e0b', bg: '#fffbeb', icon: 'phone_in_talk' },
    interested:     { label: 'Interested',      color: '#10b981', bg: '#ecfdf5', icon: 'thumb_up' },
    follow_up:      { label: 'Follow Up',       color: '#f97316', bg: '#fff7ed', icon: 'event_repeat' },
    not_interested: { label: 'Not Interested',  color: '#ef4444', bg: '#fef2f2', icon: 'thumb_down' },
    converted:      { label: 'Converted',       color: '#059669', bg: '#d1fae5', icon: 'check_circle' },
};

// ─── Aging badge ──────────────────────────────────────────────────────────────
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

// ─── Toast ────────────────────────────────────────────────────────────────────
function Toast({ message, type }) {
    if (!message) return null;
    return (
        <div style={{
            position: 'fixed', bottom: 24, right: 24, zIndex: 10000,
            padding: '12px 20px', borderRadius: 10, fontSize: 13, fontWeight: 600,
            color: '#fff', boxShadow: '0 4px 16px rgba(0,0,0,.15)',
            background: type === 'success' ? '#10b981' : '#ef4444',
            minWidth: 220, pointerEvents: 'none',
        }}>
            {message}
        </div>
    );
}

// ─── Lead Card ────────────────────────────────────────────────────────────────
function LeadCard({ lead, urls, isDragging, onDragStart, onDragEnd }) {
    return (
        <div
            className="kanban-card"
            draggable
            onDragStart={e => onDragStart(e, lead)}
            onDragEnd={onDragEnd}
            style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10,
                padding: 12, cursor: 'grab', transition: 'box-shadow .15s, transform .15s',
                opacity: isDragging ? 0.45 : 1, userSelect: 'none',
            }}
            onMouseEnter={e => { e.currentTarget.style.boxShadow = '0 6px 20px rgba(0,0,0,.10)'; e.currentTarget.style.transform = 'translateY(-2px)'; }}
            onMouseLeave={e => { e.currentTarget.style.boxShadow = ''; e.currentTarget.style.transform = ''; }}
        >
            {/* Top row */}
            <div className="d-flex justify-content-between align-items-center mb-1">
                <span style={{ fontSize: 10, fontWeight: 700, color: '#64748b', letterSpacing: '.4px' }}>
                    {lead.lead_code}
                </span>
                <AgingBadge days={lead.days_aged} />
            </div>

            {/* Name */}
            <div style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', lineHeight: 1.3, marginBottom: 6 }}>
                {lead.name}
            </div>

            {/* Phone */}
            <div className="d-flex align-items-center gap-1 mb-1" style={{ fontSize: 12, color: '#475569' }}>
                <span className="material-icons" style={{ fontSize: 13, color: '#94a3b8' }}>phone</span>
                {lead.phone}
            </div>

            {/* Course */}
            {lead.course && (
                <div className="d-flex align-items-center gap-1 mb-1" style={{ fontSize: 11, color: '#64748b' }}>
                    <span className="material-icons" style={{ fontSize: 13, color: '#94a3b8' }}>school</span>
                    {lead.course}
                </div>
            )}

            {/* Next followup */}
            {lead.next_followup && (
                <div className="d-flex align-items-center gap-1 mb-2" style={{ fontSize: 11, color: '#f97316' }}>
                    <span className="material-icons" style={{ fontSize: 13 }}>event</span>
                    {new Date(lead.next_followup).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                </div>
            )}

            {/* Footer */}
            <div className="d-flex justify-content-between align-items-center" style={{ marginTop: 4, paddingTop: 8, borderTop: '1px solid #f1f5f9' }}>
                <span style={{ fontSize: 10, color: '#94a3b8' }}>{lead.created_at}</span>
                <a
                    href={`${urls.lead_show_base}/${lead.encrypted_id}`}
                    style={{ fontSize: 11, padding: '2px 10px', background: '#eff0ff', color: '#6366f1', border: '1px solid #c7d2fe', borderRadius: 6, fontWeight: 600, textDecoration: 'none' }}
                    onClick={e => e.stopPropagation()}
                >
                    View
                </a>
            </div>
        </div>
    );
}

// ─── Pipeline Column ──────────────────────────────────────────────────────────
function PipelineColumn({ statusKey, cfg, leads, urls, draggingLead, onDragStart, onDragEnd, onDrop, onDragOver, onDragLeave, isDragOver }) {
    return (
        <div
            className="kanban-column"
            data-status={statusKey}
            onDrop={e => onDrop(e, statusKey)}
            onDragOver={e => onDragOver(e, statusKey)}
            onDragLeave={onDragLeave}
            style={{
                minWidth: 260, maxWidth: 260, background: '#fff', borderRadius: 12,
                border: `1px solid ${isDragOver ? cfg.color : '#e2e8f0'}`,
                display: 'flex', flexDirection: 'column', flexShrink: 0,
                transition: 'border-color .15s',
            }}
        >
            {/* Header */}
            <div style={{ padding: '14px 16px 12px', borderBottom: `2px solid ${cfg.color}`, borderRadius: '12px 12px 0 0', background: cfg.bg }}>
                <div className="d-flex align-items-center justify-content-between">
                    <div className="d-flex align-items-center gap-2">
                        <span className="material-icons" style={{ fontSize: 18, color: cfg.color }}>{cfg.icon}</span>
                        <span style={{ fontSize: 13, fontWeight: 700, color: cfg.color }}>{cfg.label}</span>
                    </div>
                    <span style={{ background: cfg.color, color: '#fff', fontSize: 11, fontWeight: 700, padding: '2px 9px', borderRadius: 20, minWidth: 24, textAlign: 'center' }}>
                        {leads.length}
                    </span>
                </div>
            </div>

            {/* Body */}
            <div style={{
                padding: 10, overflowY: 'auto', maxHeight: 'calc(100vh - 320px)', minHeight: 80,
                display: 'flex', flexDirection: 'column', gap: 8,
                background: isDragOver ? `${cfg.bg}88` : 'transparent',
                transition: 'background .15s',
            }}>
                {leads.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '24px 12px', color: '#94a3b8', fontSize: 12 }}>
                        <span className="material-icons" style={{ fontSize: 28, display: 'block', marginBottom: 4, opacity: .4 }}>inbox</span>
                        No leads
                    </div>
                ) : leads.map(lead => (
                    <LeadCard
                        key={lead.id}
                        lead={lead}
                        urls={urls}
                        isDragging={draggingLead?.id === lead.id}
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                    />
                ))}
            </div>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function Pipeline({ columns: initialColumns, filters, urls }) {
    const [columns, setColumns]           = useState(initialColumns ?? {});
    const [draggingLead, setDraggingLead] = useState(null);
    const [draggingFrom, setDraggingFrom] = useState(null);
    const [dragOverCol, setDragOverCol]   = useState(null);
    const [saving, setSaving]             = useState(false);
    const [toast, setToast]               = useState(null);
    const toastTimer                      = useRef(null);

    const [search,    setSearch]    = useState(filters?.search     ?? '');
    const [dateRange, setDateRange] = useState(filters?.date_range ?? '');

    function showToast(msg, type) {
        setToast({ message: msg, type });
        clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setToast(null), 3200);
    }

    function applyFilters(e) {
        e?.preventDefault();
        router.get(urls.pipeline, { search, date_range: dateRange }, { preserveState: true });
    }

    function resetFilters() {
        setSearch(''); setDateRange('');
        router.get(urls.pipeline, {}, { preserveState: false });
    }

    // ── Drag handlers ──────────────────────────────────────────────────────────
    const handleDragStart = useCallback((e, lead) => {
        // find which column this lead is in
        const fromStatus = Object.keys(columns).find(k => columns[k].some(l => l.id === lead.id));
        setDraggingLead(lead);
        setDraggingFrom(fromStatus);
        e.dataTransfer.effectAllowed = 'move';
    }, [columns]);

    const handleDragEnd = useCallback(() => {
        setDraggingLead(null);
        setDraggingFrom(null);
        setDragOverCol(null);
    }, []);

    const handleDragOver = useCallback((e, status) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        setDragOverCol(status);
    }, []);

    const handleDragLeave = useCallback(() => {
        setDragOverCol(null);
    }, []);

    const handleDrop = useCallback((e, toStatus) => {
        e.preventDefault();
        setDragOverCol(null);
        if (!draggingLead || !draggingFrom || draggingFrom === toStatus) {
            setDraggingLead(null);
            setDraggingFrom(null);
            return;
        }

        // Optimistic update
        setColumns(prev => {
            const next = { ...prev };
            next[draggingFrom] = prev[draggingFrom].filter(l => l.id !== draggingLead.id);
            next[toStatus]     = [draggingLead, ...prev[toStatus]];
            return next;
        });

        setSaving(true);

        fetch(urls.pipeline_status, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ lead_id: draggingLead.encrypted_id, status: toStatus }),
        })
        .then(r => r.json())
        .then(data => {
            setSaving(false);
            if (data.success) {
                showToast(`Moved to "${toStatus.replace('_', ' ')}"`, 'success');
            } else {
                // Revert
                setColumns(prev => {
                    const next = { ...prev };
                    next[toStatus]     = prev[toStatus].filter(l => l.id !== draggingLead.id);
                    next[draggingFrom] = [draggingLead, ...prev[draggingFrom]];
                    return next;
                });
                showToast(data.message || 'Failed to update.', 'error');
            }
        })
        .catch(() => {
            setSaving(false);
            setColumns(prev => {
                const next = { ...prev };
                next[toStatus]     = prev[toStatus].filter(l => l.id !== draggingLead.id);
                next[draggingFrom] = [draggingLead, ...prev[draggingFrom]];
                return next;
            });
            showToast('Network error — not saved.', 'error');
        });

        setDraggingLead(null);
        setDraggingFrom(null);
    }, [draggingLead, draggingFrom, urls.pipeline_status]);

    return (
        <>
            <Head title="My Lead Pipeline" />

            {/* ── Filter Bar ─────────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <form onSubmit={applyFilters} className="d-flex flex-wrap gap-3 align-items-end">
                    <div>
                        <label className="form-label mb-1" style={{ fontSize: 12, fontWeight: 600 }}>Date</label>
                        <select
                            className="form-select form-select-sm"
                            style={{ width: 140 }}
                            value={dateRange}
                            onChange={e => setDateRange(e.target.value)}
                        >
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                    <div>
                        <label className="form-label mb-1" style={{ fontSize: 12, fontWeight: 600 }}>Search</label>
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            placeholder="Name / Phone / Code"
                            style={{ width: 210 }}
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                        />
                    </div>
                    <div className="d-flex gap-2 align-items-center">
                        <button type="submit" className="btn btn-primary btn-sm px-3">Apply</button>
                        <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={resetFilters}>Reset</button>
                        <Link href={urls.leads_index} className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                            <span className="material-icons" style={{ fontSize: 16 }}>view_list</span>
                            List View
                        </Link>
                    </div>
                </form>
            </div>

            {/* ── Summary Badges ─────────────────────────────────────────────── */}
            <div className="d-flex flex-wrap gap-2 mb-3">
                {Object.entries(STATUS_CONFIG).map(([key, cfg]) => (
                    <span key={key} className="badge d-flex align-items-center gap-1 px-3 py-2"
                        style={{ background: cfg.bg, color: cfg.color, border: `1px solid ${cfg.color}20`, fontSize: 12, fontWeight: 600, borderRadius: 20 }}>
                        <span className="material-icons" style={{ fontSize: 13 }}>{cfg.icon}</span>
                        {cfg.label}
                        <span className="ms-1">{(columns[key] ?? []).length}</span>
                    </span>
                ))}
            </div>

            {/* ── Kanban Board ────────────────────────────────────────────────── */}
            <div style={{ display: 'flex', gap: 14, overflowX: 'auto', paddingBottom: 20, alignItems: 'flex-start' }}>
                {Object.entries(STATUS_CONFIG).map(([statusKey, cfg]) => (
                    <PipelineColumn
                        key={statusKey}
                        statusKey={statusKey}
                        cfg={cfg}
                        leads={columns[statusKey] ?? []}
                        urls={urls}
                        draggingLead={draggingLead}
                        onDragStart={handleDragStart}
                        onDragEnd={handleDragEnd}
                        onDrop={handleDrop}
                        onDragOver={handleDragOver}
                        onDragLeave={handleDragLeave}
                        isDragOver={dragOverCol === statusKey}
                    />
                ))}
            </div>

            {/* ── Saving overlay ──────────────────────────────────────────────── */}
            {saving && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(255,255,255,.5)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div className="spinner-border text-primary" />
                </div>
            )}

            <Toast message={toast?.message} type={toast?.type} />

            <style>{`
                #kanbanBoard::-webkit-scrollbar { height: 6px; }
                #kanbanBoard::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
                #kanbanBoard::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
            `}</style>
        </>
    );
}
