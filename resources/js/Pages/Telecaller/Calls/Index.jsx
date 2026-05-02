import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ─── Status badge ──────────────────────────────────────────────────────────────
const STATUS_CLS = {
    ringing:       'bg-warning text-dark',
    'in-progress': 'bg-info text-dark',
    answered:      'bg-info text-dark',
    completed:     'bg-success',
    missed:        'bg-danger',
    'no-answer':   'bg-danger',
    busy:          'bg-danger',
    failed:        'bg-danger',
    canceled:      'bg-danger',
};

function StatusBadge({ status }) {
    const cls = STATUS_CLS[status?.toLowerCase()] ?? 'bg-secondary';
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
    return <span className={`badge ${cls}`}>{label}</span>;
}

// ─── Direction badge ───────────────────────────────────────────────────────────
function DirectionBadge({ direction }) {
    return (
        <span className={`badge ${direction === 'inbound' ? 'bg-dark' : 'bg-primary'}`}>
            {direction === 'inbound' ? 'Inbound' : 'Outbound'}
        </span>
    );
}

// ─── Call-back button ─────────────────────────────────────────────────────────
function CallButton({ phone, leadId }) {
    const [state, setState] = useState('idle'); // idle | calling | active

    useEffect(() => {
        function onAccepted() { setState(prev => prev === 'calling' ? 'active' : prev); }
        function onEnded()    { setState('idle'); }
        document.addEventListener('gc:callAccepted', onAccepted);
        document.addEventListener('gc:callEnded',    onEnded);
        return () => {
            document.removeEventListener('gc:callAccepted', onAccepted);
            document.removeEventListener('gc:callEnded',    onEnded);
        };
    }, []);

    async function handleClick() {
        if (!window.GC) return;
        if (state === 'active' || state === 'calling') {
            window.GC.endCall();
            return;
        }
        setState('calling');
        try {
            await window.GC.startCall(phone, leadId ?? null);
        } catch (_) {
            setState('idle');
        }
    }

    const btnCls =
        state === 'active'  ? 'btn btn-sm btn-danger'         :
        state === 'calling' ? 'btn btn-sm btn-warning'        :
                              'btn btn-sm btn-outline-success';
    const icon =
        state === 'active'  ? 'call_end'      :
        state === 'calling' ? 'ring_volume'   :
                              'phone_callback';

    return (
        <button
            type="button"
            className={btnCls}
            title="Call back via integration"
            disabled={state === 'calling'}
            onClick={handleClick}
        >
            <span className="material-icons" style={{ fontSize: 16 }}>{icon}</span>
        </button>
    );
}

// ─── Call detail modal ────────────────────────────────────────────────────────
function CallDetailModal({ call, onClose }) {
    if (!call) return null;

    const rows = [
        { label: 'Lead',        value: call.lead_name ? `${call.lead_name} (${call.lead_code ?? '—'})` : '—' },
        { label: 'Phone',       value: call.lead_phone ?? call.customer_number ?? '—' },
        { label: 'Direction',   value: call.direction  ?? '—' },
        { label: 'Status',      value: call.status     ?? '—' },
        { label: 'Outcome',     value: call.outcome ? (OUTCOME_LABELS[call.outcome] ?? call.outcome) : '—' },
        { label: 'Answered At', value: call.answered_at ?? '—' },
        { label: 'Ended At',    value: call.ended_at    ?? '—' },
        { label: 'Ended By',    value: call.ended_by    ?? '—' },
        { label: 'End Reason',  value: call.end_reason  ?? '—' },
        { label: 'Call SID',    value: call.call_sid    ?? '—' },
    ];

    return (
        <div
            className="modal fade show d-block"
            tabIndex="-1"
            role="dialog"
            style={{ background: 'rgba(0,0,0,.45)' }}
            onClick={onClose}
        >
            <div
                className="modal-dialog modal-dialog-centered"
                role="document"
                onClick={e => e.stopPropagation()}
            >
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">Call Details</h5>
                        <button type="button" className="btn-close" onClick={onClose} />
                    </div>
                    <div className="modal-body p-0">
                        <table className="table table-sm table-bordered mb-0">
                            <tbody>
                                {rows.map(r => (
                                    <tr key={r.label}>
                                        <th
                                            className="ps-3 py-2 text-muted fw-semibold"
                                            style={{ width: '35%', background: '#f8fafc' }}
                                        >
                                            {r.label}
                                        </th>
                                        <td className="ps-3 py-2">{r.value}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Tab config ───────────────────────────────────────────────────────────────
const TABS = [
    { key: 'outbound', href: '/telecaller/calls/outbound', label: 'Outbound Calls', activeCls: 'btn-primary',  inactiveCls: 'btn-outline-primary' },
    { key: 'inbound',  href: '/telecaller/calls/inbound',  label: 'Inbound Calls',  activeCls: 'btn-primary',  inactiveCls: 'btn-outline-primary' },
    { key: 'missed',   href: '/telecaller/calls/missed',   label: 'Missed Calls',   activeCls: 'btn-danger',   inactiveCls: 'btn-outline-danger'  },
    { key: 'history',  href: '/telecaller/calls/history',  label: 'Call History',   activeCls: 'btn-dark',     inactiveCls: 'btn-outline-dark'    },
];

// ─── Main page ────────────────────────────────────────────────────────────────
const OUTCOME_LABELS = {
    interested:      'Interested',
    not_interested:  'Not Interested',
    wrong_number:    'Wrong Number',
    call_back_later: 'Call Back Later',
    switched_off:    'Switched Off',
};

export default function Index({ scope, title, callLogs, statusOptions, outcomeOptions, filters }) {
    const [date,       setDate]       = useState(filters?.date      ?? '');
    const [dateFrom,   setDateFrom]   = useState(filters?.date_from ?? '');
    const [dateTo,     setDateTo]     = useState(filters?.date_to   ?? '');
    const [status,     setStatus]     = useState(filters?.status    ?? '');
    const [outcome,    setOutcome]    = useState(filters?.outcome   ?? '');
    const [activeCall, setActiveCall] = useState(null);

    const isDrilldown = !!(filters?.date_from || filters?.date_to);

    function applyFilters(e) {
        e.preventDefault();
        const params = {};
        if (date)     params.date      = date;
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo)   params.date_to   = dateTo;
        if (status)   params.status    = status;
        if (outcome)  params.outcome   = outcome;
        router.get(`/telecaller/calls/${scope}`, params, { preserveScroll: true });
    }

    function resetFilters() {
        setDate(''); setDateFrom(''); setDateTo('');
        setStatus(''); setOutcome('');
        router.get(`/telecaller/calls/${scope}`, {}, { preserveScroll: true });
    }

    function exportUrl(format) {
        const p = new URLSearchParams({ format });
        if (date)    p.set('date',    date);
        if (status)  p.set('status',  status);
        if (outcome) p.set('outcome', outcome);
        return `/telecaller/calls/${scope}/export?${p.toString()}`;
    }

    return (
        <>
            <Head title={title} />

            {activeCall && (
                <CallDetailModal call={activeCall} onClose={() => setActiveCall(null)} />
            )}

            {/* ── Scope tabs ─────────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <div className="chart-header mb-2">
                    <h3>{title}</h3>
                    <p>Track all your calls with status and actions</p>
                </div>
                <div className="d-flex gap-2 flex-wrap">
                    {TABS.map(tab => (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            className={`btn btn-sm ${scope === tab.key ? tab.activeCls : tab.inactiveCls}`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>
            </div>

            {/* ── Filters ────────────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <form className="row g-3 align-items-end" onSubmit={applyFilters}>
                    <div className="col-md-3">
                        <label className="form-label">Date</label>
                        <input
                            type="date"
                            className="form-control"
                            value={date}
                            onChange={e => setDate(e.target.value)}
                        />
                    </div>

                    <div className="col-md-3">
                        <label className="form-label">Status</label>
                        <select className="form-select" value={status} onChange={e => setStatus(e.target.value)}>
                            <option value="">All</option>
                            {statusOptions.map(s => (
                                <option key={s} value={s}>
                                    {s.charAt(0).toUpperCase() + s.slice(1)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-3">
                        <label className="form-label">Outcome</label>
                        <select className="form-select" value={outcome} onChange={e => setOutcome(e.target.value)}>
                            <option value="">All</option>
                            {(outcomeOptions ?? []).map(o => (
                                <option key={o} value={o}>{OUTCOME_LABELS[o] ?? o}</option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-3 d-flex gap-2 flex-wrap">
                        <button type="submit" className="btn btn-primary flex-fill">Apply</button>
                        <button type="button" className="btn btn-outline-secondary flex-fill" onClick={resetFilters}>
                            Reset
                        </button>
                    </div>

                    {/* Export row */}
                    <div className="col-12 d-flex justify-content-end">
                        <div className="dropdown">
                            <button
                                type="button"
                                className="btn btn-outline-success btn-sm px-3 d-flex align-items-center gap-1"
                                data-bs-toggle="dropdown"
                            >
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

            {/* ── Table ─────────────────────────────────────────────────────── */}
            <div className="custom-table">
                {isDrilldown && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 18px', background: '#6366f110', borderBottom: '1px solid #e2e8f0' }}>
                        <span className="material-icons" style={{ fontSize: 16, color: '#6366f1' }}>filter_alt</span>
                        <span style={{ fontSize: 12, color: '#6366f1', fontWeight: 600 }}>
                            Filtered from My Performance &nbsp;·&nbsp; {dateFrom} → {dateTo}
                            {outcome && <> &nbsp;·&nbsp; Outcome: <strong>{outcome.replace(/_/g, ' ')}</strong></>}
                        </span>
                        <button type="button" className="btn btn-sm btn-link p-0 ms-2" style={{ fontSize: 11, color: '#94a3b8' }} onClick={resetFilters}>
                            Clear filter
                        </button>
                    </div>
                )}
                <div className="table-header">
                    <h3>{title} List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>{callLogs.total} records</span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Lead</th>
                                {scope !== 'missed' && <th>Duration</th>}
                                {scope !== 'missed' && <th>Outcome</th>}
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {callLogs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={scope === 'missed' ? 5 : 7} className="text-center py-4 text-muted">
                                        No calls found.
                                    </td>
                                </tr>
                            ) : callLogs.data.map((call, idx) => {
                                const sno            = (callLogs.current_page - 1) * callLogs.per_page + idx + 1;
                                const callbackNumber = call.lead_phone || call.customer_number;

                                return (
                                    <tr key={call.id}>
                                        <td>{sno}</td>
                                        <td>{call.created_at_fmt}</td>
                                        <td>
                                            <div className="fw-semibold">{call.lead_name ?? 'N/A'}</div>
                                            <small className="text-muted">
                                                {call.lead_code ?? '—'} | {call.lead_phone ?? call.customer_number ?? '—'}
                                            </small>
                                        </td>
                                        {scope !== 'missed' && <td className="fw-semibold">{call.duration_fmt}</td>}
                                        {scope !== 'missed' && <td>{call.outcome ? (OUTCOME_LABELS[call.outcome] ?? call.outcome) : <span className="text-muted">—</span>}</td>}
                                        <td><StatusBadge status={call.status} /></td>
                                        <td>
                                            <div className="d-flex gap-1">
                                                {/* View detail modal */}
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-outline-primary"
                                                    title="View call details"
                                                    onClick={() => setActiveCall(call)}
                                                >
                                                    <span className="material-icons" style={{ fontSize: 16 }}>visibility</span>
                                                </button>

                                                {/* Call back */}
                                                {callbackNumber && (
                                                    <CallButton phone={callbackNumber} leadId={call.lead_id} />
                                                )}
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
                    <small className="text-muted">
                        Showing {callLogs.from ?? 0}–{callLogs.to ?? 0} of {callLogs.total} results
                    </small>
                    {callLogs.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {callLogs.links.map((link, i) => (
                                    <li key={i} className={[
                                        'page-item',
                                        link.active ? 'active'   : '',
                                        !link.url   ? 'disabled' : '',
                                    ].join(' ')}>
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span
                                                className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
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
