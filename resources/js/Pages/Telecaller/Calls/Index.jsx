import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ─── Design tokens ─────────────────────────────────────────────────────────────
const ORG   = '#FF5C1A';
const ORGL  = '#FF8042';
const DARKC = '#1A1A2E';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const WHITE  = '#FFFFFF';
const BGGRAY = '#F5F5F7';

const card = (e = {}) => ({
    background: WHITE,
    borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...e,
});

const selStyle = (e = {}) => ({
    fontSize: 13,
    color: TEXT,
    border: `1px solid ${BORDER}`,
    borderRadius: 10,
    padding: '9px 14px',
    background: WHITE,
    cursor: 'pointer',
    appearance: 'none',
    WebkitAppearance: 'none',
    ...e,
});

const TH_STYLE = {
    padding: '11px 16px',
    fontSize: 10.5,
    fontWeight: 700,
    color: MUTED,
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
    background: '#F8F9FB',
    borderBottom: `1px solid ${BORDER}`,
};

const TD_STYLE = {
    padding: '12px 16px',
    fontSize: 13,
    color: TEXT,
    borderBottom: `1px solid ${BORDER}`,
};

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

const STATUS_PILL = {
    ringing:       { background: '#FEF3C7', color: '#92400E' },
    'in-progress': { background: '#DBEAFE', color: '#1E40AF' },
    answered:      { background: '#DBEAFE', color: '#1E40AF' },
    completed:     { background: '#D1FAE5', color: '#065F46' },
    missed:        { background: '#FEE2E2', color: '#991B1B' },
    'no-answer':   { background: '#FEE2E2', color: '#991B1B' },
    busy:          { background: '#FEE2E2', color: '#991B1B' },
    failed:        { background: '#FEE2E2', color: '#991B1B' },
    canceled:      { background: '#FEE2E2', color: '#991B1B' },
};

function StatusBadge({ status }) {
    const pill  = STATUS_PILL[status?.toLowerCase()] ?? { background: '#E5E7EB', color: '#374151' };
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
    return (
        <span style={{
            display: 'inline-block',
            borderRadius: 20,
            fontSize: 11,
            fontWeight: 700,
            padding: '3px 10px',
            ...pill,
        }}>
            {label}
        </span>
    );
}

// ─── Direction badge ───────────────────────────────────────────────────────────
function DirectionBadge({ direction }) {
    const isInbound = direction === 'inbound';
    return (
        <span style={{
            display: 'inline-block',
            borderRadius: 20,
            fontSize: 11,
            fontWeight: 700,
            padding: '3px 10px',
            background: isInbound ? DARKC : ORG,
            color: WHITE,
        }}>
            {isInbound ? 'Inbound' : 'Outbound'}
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

    const bgColor =
        state === 'active'  ? '#EF4444' :
        state === 'calling' ? '#F59E0B' :
                              '#10B981';

    const icon =
        state === 'active'  ? 'call_end'      :
        state === 'calling' ? 'ring_volume'   :
                              'phone_callback';

    return (
        <button
            type="button"
            title="Call back via integration"
            disabled={state === 'calling'}
            onClick={handleClick}
            style={{
                background: bgColor,
                color: WHITE,
                border: 'none',
                borderRadius: 8,
                width: 32,
                height: 32,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: state === 'calling' ? 'not-allowed' : 'pointer',
                opacity: state === 'calling' ? 0.7 : 1,
                flexShrink: 0,
            }}
        >
            {state === 'calling' ? (
                <span style={{
                    width: 14,
                    height: 14,
                    border: `2px solid ${WHITE}`,
                    borderTopColor: 'transparent',
                    borderRadius: '50%',
                    display: 'inline-block',
                    animation: 'spin 0.7s linear infinite',
                }} />
            ) : (
                <span className="material-icons" style={{ fontSize: 16 }}>{icon}</span>
            )}
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
                <div className="modal-content" style={{ borderRadius: 16, border: `1px solid ${BORDER}`, boxShadow: '0 8px 40px rgba(0,0,0,0.13)' }}>
                    <div className="modal-header" style={{ borderBottom: `1px solid ${BORDER}`, padding: '18px 24px' }}>
                        <h5 className="modal-title" style={{ fontSize: 16, fontWeight: 700, color: TEXT, margin: 0 }}>
                            Call Details
                        </h5>
                        <button type="button" className="btn-close" onClick={onClose} />
                    </div>
                    <div className="modal-body" style={{ padding: 0 }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <tbody>
                                {rows.map(r => (
                                    <tr key={r.label} style={{ borderBottom: `1px solid ${BORDER}` }}>
                                        <th style={{
                                            padding: '10px 20px',
                                            fontSize: 12,
                                            fontWeight: 600,
                                            color: MUTED,
                                            background: BGGRAY,
                                            width: '35%',
                                            textAlign: 'left',
                                        }}>
                                            {r.label}
                                        </th>
                                        <td style={{ padding: '10px 20px', fontSize: 13, color: TEXT }}>{r.value}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="modal-footer" style={{ borderTop: `1px solid ${BORDER}`, padding: '14px 24px' }}>
                        <button
                            type="button"
                            onClick={onClose}
                            style={{
                                background: BGGRAY,
                                color: TEXT,
                                border: `1px solid ${BORDER}`,
                                borderRadius: 8,
                                padding: '7px 18px',
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
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
    { key: 'outbound', href: '/telecaller/calls/outbound', label: 'Outbound Calls' },
    { key: 'inbound',  href: '/telecaller/calls/inbound',  label: 'Inbound Calls'  },
    { key: 'missed',   href: '/telecaller/calls/missed',   label: 'Missed Calls'   },
    { key: 'history',  href: '/telecaller/calls/history',  label: 'Call History'   },
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
    const [hoveredRow, setHoveredRow] = useState(null);

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

    const labelStyle = {
        fontSize: 12,
        fontWeight: 600,
        color: MUTED,
        display: 'block',
        marginBottom: 6,
        textTransform: 'uppercase',
        letterSpacing: '0.4px',
    };

    const inputStyle = {
        ...selStyle(),
        width: '100%',
        boxSizing: 'border-box',
    };

    return (
        <>
            <Head title={title} />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                @keyframes spin { to { transform: rotate(360deg); } }
            `}</style>

            {activeCall && (
                <CallDetailModal call={activeCall} onClose={() => setActiveCall(null)} />
            )}

            {/* ── Header + Tab navigation ─────────────────────────────────────── */}
            <div style={{ ...card(), padding: '20px 24px', marginBottom: 20 }}>
                <div style={{ marginBottom: 16 }}>
                    <h3 style={{ fontSize: 20, fontWeight: 700, color: TEXT, margin: 0 }}>{title}</h3>
                    <p style={{ fontSize: 13, color: MUTED, margin: '4px 0 0' }}>Track all your calls with status and actions</p>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {TABS.map(tab => {
                        const isActive = scope === tab.key;
                        return (
                            <Link
                                key={tab.key}
                                href={tab.href}
                                style={{
                                    display: 'inline-block',
                                    padding: '7px 18px',
                                    borderRadius: 20,
                                    fontSize: 13,
                                    fontWeight: 600,
                                    textDecoration: 'none',
                                    border: `1.5px solid ${isActive ? ORG : BORDER}`,
                                    background: isActive ? ORG : WHITE,
                                    color: isActive ? WHITE : TEXT,
                                    transition: 'all 0.15s',
                                }}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </div>
            </div>

            {/* ── Filters ────────────────────────────────────────────────────── */}
            <div style={{ ...card(), padding: '20px 24px', marginBottom: 20 }}>
                <form onSubmit={applyFilters}>
                    <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                        <div style={{ flex: '1 1 160px', minWidth: 140 }}>
                            <label style={labelStyle}>Date</label>
                            <input
                                type="date"
                                style={inputStyle}
                                value={date}
                                onChange={e => setDate(e.target.value)}
                            />
                        </div>

                        <div style={{ flex: '1 1 160px', minWidth: 140, position: 'relative' }}>
                            <label style={labelStyle}>Status</label>
                            <select
                                style={{ ...inputStyle, paddingRight: 36 }}
                                value={status}
                                onChange={e => setStatus(e.target.value)}
                            >
                                <option value="">All</option>
                                {statusOptions.map(s => (
                                    <option key={s} value={s}>
                                        {s.charAt(0).toUpperCase() + s.slice(1)}
                                    </option>
                                ))}
                            </select>
                            <span className="material-icons" style={{ position: 'absolute', right: 12, bottom: 10, fontSize: 16, color: MUTED, pointerEvents: 'none' }}>expand_more</span>
                        </div>

                        <div style={{ flex: '1 1 160px', minWidth: 140, position: 'relative' }}>
                            <label style={labelStyle}>Outcome</label>
                            <select
                                style={{ ...inputStyle, paddingRight: 36 }}
                                value={outcome}
                                onChange={e => setOutcome(e.target.value)}
                            >
                                <option value="">All</option>
                                {(outcomeOptions ?? []).map(o => (
                                    <option key={o} value={o}>{OUTCOME_LABELS[o] ?? o}</option>
                                ))}
                            </select>
                            <span className="material-icons" style={{ position: 'absolute', right: 12, bottom: 10, fontSize: 16, color: MUTED, pointerEvents: 'none' }}>expand_more</span>
                        </div>

                        <div style={{ display: 'flex', gap: 8, flexShrink: 0, alignItems: 'flex-end' }}>
                            <button
                                type="submit"
                                style={{
                                    background: ORG,
                                    color: WHITE,
                                    border: 'none',
                                    borderRadius: 10,
                                    padding: '9px 20px',
                                    fontSize: 13,
                                    fontWeight: 700,
                                    cursor: 'pointer',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onClick={resetFilters}
                                style={{
                                    background: WHITE,
                                    color: TEXT,
                                    border: `1px solid ${BORDER}`,
                                    borderRadius: 10,
                                    padding: '9px 20px',
                                    fontSize: 13,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                Reset
                            </button>
                        </div>

                        {/* Export */}
                        <div style={{ marginLeft: 'auto', flexShrink: 0, alignSelf: 'flex-end' }}>
                            <div className="dropdown">
                                <button
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 6,
                                        background: WHITE,
                                        border: `1px solid ${BORDER}`,
                                        borderRadius: 10,
                                        padding: '9px 16px',
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: TEXT,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <span className="material-icons" style={{ fontSize: 15, color: '#10B981' }}>download</span>
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
                    </div>
                </form>
            </div>

            {/* ── Table ─────────────────────────────────────────────────────── */}
            <div style={card()}>
                {isDrilldown && (
                    <div style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        padding: '10px 20px',
                        background: '#EEF2FF',
                        borderBottom: `1px solid #C7D2FE`,
                        borderRadius: '14px 14px 0 0',
                    }}>
                        <span className="material-icons" style={{ fontSize: 16, color: '#6366F1' }}>filter_alt</span>
                        <span style={{ fontSize: 12, color: '#6366F1', fontWeight: 600 }}>
                            Filtered from My Performance &nbsp;·&nbsp; {dateFrom} → {dateTo}
                            {outcome && <> &nbsp;·&nbsp; Outcome: <strong>{outcome.replace(/_/g, ' ')}</strong></>}
                        </span>
                        <button
                            type="button"
                            onClick={resetFilters}
                            style={{
                                background: 'none',
                                border: 'none',
                                fontSize: 11,
                                color: MUTED,
                                cursor: 'pointer',
                                marginLeft: 8,
                                padding: 0,
                                fontWeight: 600,
                            }}
                        >
                            Clear filter
                        </button>
                    </div>
                )}

                {/* Toolbar */}
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '16px 20px',
                    borderBottom: `1px solid ${BORDER}`,
                }}>
                    <div>
                        <span style={{ fontSize: 15, fontWeight: 700, color: TEXT }}>{title} List</span>
                        <span style={{ marginLeft: 10, fontSize: 12, color: MUTED, fontWeight: 500 }}>
                            {callLogs.total} records
                        </span>
                    </div>
                </div>

                {/* Table */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={TH_STYLE}>S.No</th>
                                <th style={TH_STYLE}>Date</th>
                                <th style={TH_STYLE}>Lead</th>
                                {scope !== 'missed' && <th style={TH_STYLE}>Duration</th>}
                                {scope !== 'missed' && <th style={TH_STYLE}>Outcome</th>}
                                <th style={TH_STYLE}>Status</th>
                                <th style={TH_STYLE}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {callLogs.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={scope === 'missed' ? 5 : 7}
                                        style={{ ...TD_STYLE, border: 'none' }}
                                    >
                                        <div style={{ textAlign: 'center', padding: '52px 0 44px' }}>
                                            <div style={{
                                                width: 72, height: 72, borderRadius: 20,
                                                background: scope === 'missed' ? '#FEF2F2' : '#EEF2FF',
                                                display: 'flex', alignItems: 'center',
                                                justifyContent: 'center', margin: '0 auto 16px',
                                            }}>
                                                <span className="material-icons" style={{
                                                    fontSize: 36,
                                                    color: scope === 'missed' ? '#FCA5A5' : '#A5B4FC',
                                                }}>
                                                    {scope === 'missed' ? 'phone_missed' : 'phone_in_talk'}
                                                </span>
                                            </div>
                                            <div style={{ fontSize: 15, fontWeight: 700, color: TEXT, marginBottom: 6 }}>
                                                {scope === 'missed' ? 'No missed calls' : 'No calls yet'}
                                            </div>
                                            <div style={{ fontSize: 13, color: MUTED, maxWidth: 260, margin: '0 auto' }}>
                                                {scope === 'missed'
                                                    ? 'Great work — no missed calls in this period.'
                                                    : 'Calls will appear here once you start making or receiving them.'}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            ) : callLogs.data.map((call, idx) => {
                                const sno            = (callLogs.current_page - 1) * callLogs.per_page + idx + 1;
                                const callbackNumber = call.lead_phone || call.customer_number;
                                const isHovered      = hoveredRow === call.id;

                                return (
                                    <tr
                                        key={call.id}
                                        onMouseEnter={() => setHoveredRow(call.id)}
                                        onMouseLeave={() => setHoveredRow(null)}
                                        style={{ background: isHovered ? '#FAFAFA' : WHITE, transition: 'background 0.12s' }}
                                    >
                                        <td style={{ ...TD_STYLE, color: MUTED, fontWeight: 600 }}>{sno}</td>
                                        <td style={{ ...TD_STYLE, whiteSpace: 'nowrap' }}>{call.created_at_fmt}</td>
                                        <td style={TD_STYLE}>
                                            <div style={{ fontWeight: 600, color: TEXT }}>{call.lead_name ?? 'N/A'}</div>
                                            <div style={{ fontSize: 11, color: MUTED }}>
                                                {call.lead_code ?? '—'} | {call.lead_phone ?? call.customer_number ?? '—'}
                                            </div>
                                        </td>
                                        {scope !== 'missed' && (
                                            <td style={{ ...TD_STYLE, fontFamily: 'monospace', fontSize: 13, fontWeight: 600 }}>
                                                {call.duration_fmt}
                                            </td>
                                        )}
                                        {scope !== 'missed' && (
                                            <td style={TD_STYLE}>
                                                {call.outcome
                                                    ? (OUTCOME_LABELS[call.outcome] ?? call.outcome)
                                                    : <span style={{ color: MUTED }}>—</span>
                                                }
                                            </td>
                                        )}
                                        <td style={TD_STYLE}>
                                            <StatusBadge status={call.status} />
                                        </td>
                                        <td style={TD_STYLE}>
                                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                {/* View detail modal */}
                                                <button
                                                    type="button"
                                                    title="View call details"
                                                    onClick={() => setActiveCall(call)}
                                                    style={{
                                                        background: ORG,
                                                        color: WHITE,
                                                        border: 'none',
                                                        borderRadius: 8,
                                                        width: 32,
                                                        height: 32,
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        cursor: 'pointer',
                                                        flexShrink: 0,
                                                    }}
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

                {/* ── Pagination ──────────────────────────────────────────────── */}
                <div style={{
                    padding: '14px 20px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: 10,
                    borderTop: `1px solid ${BORDER}`,
                }}>
                    <span style={{ fontSize: 12, color: MUTED }}>
                        Showing {callLogs.from ?? 0}–{callLogs.to ?? 0} of {callLogs.total} results
                    </span>
                    {callLogs.last_page > 1 && (
                        <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                            {callLogs.links.map((link, i) => {
                                const isActive   = link.active;
                                const isDisabled = !link.url;
                                const base = {
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    minWidth: 34,
                                    height: 34,
                                    padding: '0 10px',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    fontWeight: isActive ? 700 : 500,
                                    border: `1px solid ${isActive ? ORG : BORDER}`,
                                    background: isActive ? ORG : WHITE,
                                    color: isActive ? WHITE : isDisabled ? MUTED : TEXT,
                                    textDecoration: 'none',
                                    cursor: isDisabled ? 'default' : 'pointer',
                                    opacity: isDisabled ? 0.5 : 1,
                                    boxShadow: isActive ? '0 2px 8px rgba(255,92,26,0.18)' : 'none',
                                };
                                return link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        style={base}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        style={base}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
