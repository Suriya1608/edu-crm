import { Head, Link } from '@inertiajs/react';
import ReportFilters from './_Filters';

const NAV = [
    { href: '/manager/reports',                        label: 'Overview'              },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance' },
    { href: '/manager/reports/conversion',             label: 'Conversion'            },
    { href: '/manager/reports/source-performance',     label: 'Source Performance'    },
    { href: '/manager/reports/period',                 label: 'Period Analysis'       },
    { href: '/manager/reports/response-time',          label: 'Response Time'         },
    { href: '/manager/reports/call-efficiency',        label: 'Call Efficiency'       },
];

function formatDuration(seconds) {
    const s = Math.round(parseFloat(seconds) || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return [h, m, sec].map(v => String(v).padStart(2, '0')).join(':');
}

export default function CallEfficiency({ filters, filterOptions, rows }) {
    const totalCalls     = (rows ?? []).reduce((s, r) => s + (r.total_calls ?? 0), 0);
    const completedCalls = (rows ?? []).reduce((s, r) => s + (r.completed_calls ?? 0), 0);
    const missedCalls    = (rows ?? []).reduce((s, r) => s + (r.missed_calls ?? 0), 0);

    return (
        <>
            <Head title="Call Efficiency" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/call-efficiency' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/call-efficiency" />

            <div className="row g-3 mb-4">
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">call</span></div>
                        <div className="stat-label">Total Calls</div>
                        <div className="stat-value">{totalCalls.toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">call_received</span></div>
                        <div className="stat-label">Completed</div>
                        <div className="stat-value">{completedCalls.toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon red"><span className="material-icons">phone_missed</span></div>
                        <div className="stat-label">Missed</div>
                        <div className="stat-value">{missedCalls.toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">trending_up</span></div>
                        <div className="stat-label">Overall Rate</div>
                        <div className="stat-value">
                            {totalCalls > 0 ? `${Math.round((completedCalls / totalCalls) * 100)}%` : '—'}
                        </div>
                    </div>
                </div>
            </div>

            <div className="custom-table">
                <div className="table-header">
                    <h3>Call Efficiency by Telecaller</h3>
                    <a href="/manager/reports/export/call-efficiency/excel" className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export CSV
                    </a>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>Telecaller</th>
                                <th>Total Calls</th>
                                <th>Completed</th>
                                <th>Missed</th>
                                <th>Avg Duration</th>
                                <th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(rows ?? []).length === 0 ? (
                                <tr><td colSpan={6} className="text-center py-5 text-muted">No data for selected period.</td></tr>
                            ) : rows.map((r, i) => (
                                <tr key={i}>
                                    <td className="fw-semibold">{r.telecaller_name}</td>
                                    <td>{r.total_calls}</td>
                                    <td><span className="badge bg-success">{r.completed_calls}</span></td>
                                    <td><span className="badge bg-danger">{r.missed_calls}</span></td>
                                    <td>{formatDuration(r.avg_duration)}</td>
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <div className="progress flex-grow-1" style={{ height: 6 }}>
                                                <div className="progress-bar bg-primary" style={{ width: `${r.completion_rate}%` }} />
                                            </div>
                                            <span style={{ fontSize: 12, whiteSpace: 'nowrap' }}>{r.completion_rate}%</span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
