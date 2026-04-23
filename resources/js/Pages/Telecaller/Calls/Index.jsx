import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ─── Status badge ──────────────────────────────────────────────────────────────
const STATUS_CLS = {
    ringing:     'bg-warning text-dark',
    'in-progress': 'bg-info text-dark',
    answered:    'bg-info text-dark',
    completed:   'bg-success',
    missed:      'bg-danger',
    'no-answer': 'bg-danger',
    busy:        'bg-danger',
    failed:      'bg-danger',
    canceled:    'bg-danger',
};

function StatusBadge({ status }) {
    const cls = STATUS_CLS[status?.toLowerCase()] ?? 'bg-secondary';
    return <span className={`badge ${cls}`}>{status || '—'}</span>;
}

// ─── Direction badge ───────────────────────────────────────────────────────────
function DirectionBadge({ direction }) {
    return (
        <span className={`badge ${direction === 'inbound' ? 'bg-dark' : 'bg-primary'}`}>
            {direction === 'inbound' ? 'Inbound' : 'Outbound'}
        </span>
    );
}

// ─── Call-back button with idle / calling / active states ─────────────────────
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

// ─── Tab config ───────────────────────────────────────────────────────────────
const TABS = [
    { key: 'outbound', href: '/telecaller/calls/outbound', label: 'Outbound Calls', activeCls: 'btn-primary',        inactiveCls: 'btn-outline-primary' },
    { key: 'inbound',  href: '/telecaller/calls/inbound',  label: 'Inbound Calls',  activeCls: 'btn-primary',        inactiveCls: 'btn-outline-primary' },
    { key: 'missed',   href: '/telecaller/calls/missed',   label: 'Missed Calls',   activeCls: 'btn-danger',         inactiveCls: 'btn-outline-danger'  },
    { key: 'history',  href: '/telecaller/calls/history',  label: 'Call History',   activeCls: 'btn-dark',           inactiveCls: 'btn-outline-dark'    },
];

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ scope, title, callLogs, statusOptions, filters }) {
    const [date,   setDate]   = useState(filters?.date   ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');

    function applyFilters(e) {
        e.preventDefault();
        router.get(`/telecaller/calls/${scope}`, { date, status }, { preserveScroll: true });
    }

    function resetFilters() {
        setDate('');
        setStatus('');
        router.get(`/telecaller/calls/${scope}`, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={title} />

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
                    <div className="col-md-4">
                        <label className="form-label">Date</label>
                        <input
                            type="date"
                            className="form-control"
                            value={date}
                            onChange={e => setDate(e.target.value)}
                        />
                    </div>

                    <div className="col-md-4">
                        <label className="form-label">Status</label>
                        <select
                            className="form-select"
                            value={status}
                            onChange={e => setStatus(e.target.value)}
                        >
                            <option value="">All</option>
                            {statusOptions.map(s => (
                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-4 d-flex gap-2">
                        <button type="submit" className="btn btn-primary w-100">Apply</button>
                        <button type="button" className="btn btn-outline-secondary w-100" onClick={resetFilters}>
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            {/* ── Table ─────────────────────────────────────────────────────── */}
            <div className="custom-table">
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
                                <th>Type</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Recording</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {callLogs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-4 text-muted">
                                        No calls found.
                                    </td>
                                </tr>
                            ) : callLogs.data.map((call, idx) => {
                                const sno = (callLogs.current_page - 1) * callLogs.per_page + idx + 1;
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
                                        <td><DirectionBadge direction={call.direction} /></td>
                                        <td><StatusBadge status={call.status} /></td>
                                        <td className="fw-semibold">{call.duration_fmt}</td>
                                        <td>
                                            {call.recording_url ? (
                                                <audio controls preload="none" style={{ maxWidth: 170, height: 32 }}>
                                                    <source src={call.recording_url} />
                                                </audio>
                                            ) : (
                                                <span className="text-muted">N/A</span>
                                            )}
                                        </td>
                                        <td>
                                            {callbackNumber ? (
                                                <CallButton phone={callbackNumber} leadId={call.lead_id} />
                                            ) : (
                                                <span className="text-muted">—</span>
                                            )}
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
