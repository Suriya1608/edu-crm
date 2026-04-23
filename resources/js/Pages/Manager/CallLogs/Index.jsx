import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatDuration(seconds) {
    const s = parseInt(seconds) || 0;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return [h, m, sec].map(v => String(v).padStart(2, '0')).join(':');
}

const SCOPE_LABELS = {
    all:      'All Calls',
    inbound:  'Inbound Calls',
    outbound: 'Outbound Calls',
    missed:   'Missed Calls',
};

const SCOPE_TABS = [
    { key: 'all',      label: 'All',      href: '?scope=all'      },
    { key: 'inbound',  label: 'Inbound',  href: '?scope=inbound'  },
    { key: 'outbound', label: 'Outbound', href: '?scope=outbound' },
    { key: 'missed',   label: 'Missed',   href: '?scope=missed'   },
];

export default function Index({ callLogs, telecallers, statusOptions, scope, filters }) {
    const [date, setDate]           = useState(filters?.date       ?? '');
    const [telecaller, setTelecaller] = useState(filters?.telecaller ?? '');
    const [status, setStatus]       = useState(filters?.status     ?? '');

    function applyFilters(e) {
        e.preventDefault();
        const params = { scope };
        if (date)       params.date       = date;
        if (telecaller) params.telecaller = telecaller;
        if (status)     params.status     = status;
        router.get('/manager/call-logs', params, { preserveState: false });
    }

    function resetFilters() {
        setDate(''); setTelecaller(''); setStatus('');
        router.get('/manager/call-logs', { scope }, { preserveState: false });
    }

    function switchScope(newScope) {
        const params = { scope: newScope };
        if (date)       params.date       = date;
        if (telecaller) params.telecaller = telecaller;
        if (status)     params.status     = status;
        router.get('/manager/call-logs', params, { preserveState: false });
    }

    return (
        <>
            <Head title="Call Logs" />

            {/* ── Scope tabs ─────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <div className="chart-header mb-2">
                    <h3>{SCOPE_LABELS[scope] ?? 'Call Logs'}</h3>
                    <span className="badge bg-light text-dark">{callLogs.total} records</span>
                </div>
                <div className="d-flex gap-2 flex-wrap">
                    {SCOPE_TABS.map(tab => (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => switchScope(tab.key)}
                            className={`btn btn-sm ${scope === tab.key ? 'btn-primary' : 'btn-outline-primary'}`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* ── Filters ────────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <form onSubmit={applyFilters} className="row g-3 align-items-end">
                    <div className="col-md-3">
                        <label className="form-label">Date</label>
                        <input type="date" className="form-control" value={date} onChange={e => setDate(e.target.value)} />
                    </div>
                    <div className="col-md-3">
                        <label className="form-label">Telecaller</label>
                        <select className="form-select" value={telecaller} onChange={e => setTelecaller(e.target.value)}>
                            <option value="">All</option>
                            {telecallers.map(t => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="col-md-3">
                        <label className="form-label">Status</label>
                        <select className="form-select" value={status} onChange={e => setStatus(e.target.value)}>
                            <option value="">All</option>
                            {statusOptions.map(s => (
                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                            ))}
                        </select>
                    </div>
                    <div className="col-md-3 d-flex gap-2">
                        <button type="submit" className="btn btn-primary w-100">Apply</button>
                        <button type="button" className="btn btn-outline-secondary w-100" onClick={resetFilters}>Reset</button>
                    </div>
                </form>
            </div>

            {/* ── Table ──────────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>Call Log List</h3>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Lead ID</th>
                                <th>Lead</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Telecaller</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {callLogs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-5 text-muted">
                                        <span className="material-icons d-block mb-2" style={{ fontSize: 40, opacity: 0.3 }}>phone_missed</span>
                                        No call logs found.
                                    </td>
                                </tr>
                            ) : callLogs.data.map((call, idx) => {
                                const sno = (callLogs.current_page - 1) * callLogs.per_page + idx + 1;
                                return (
                                    <tr key={call.id}>
                                        <td>{sno}</td>
                                        <td style={{ whiteSpace: 'nowrap' }}>{call.created_at}</td>
                                        <td>{call.lead_code}</td>
                                        <td>
                                            <div className="fw-semibold">{call.lead_name}</div>
                                            <small className="text-muted">{call.lead_phone}</small>
                                        </td>
                                        <td>
                                            <span className={`badge ${call.type === 'outbound' ? 'bg-primary' : 'bg-secondary'}`}>
                                                {call.type.charAt(0).toUpperCase() + call.type.slice(1)}
                                            </span>
                                        </td>
                                        <td><span className="badge bg-light text-dark">{call.status}</span></td>
                                        <td className="fw-semibold">{formatDuration(call.duration)}</td>
                                        <td>{call.telecaller}</td>
                                        <td>
                                            {call.encrypted_lead_id && (
                                                <Link href={`/manager/leads/${call.encrypted_lead_id}`}
                                                    className="btn btn-sm btn-outline-primary" title="View Lead">
                                                    <span className="material-icons" style={{ fontSize: 16 }}>open_in_new</span>
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {callLogs.from ?? 0}–{callLogs.to ?? 0} of {callLogs.total} results
                    </small>
                    {callLogs.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {callLogs.links.map((link, i) => (
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
