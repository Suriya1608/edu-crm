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

function fmtMinutes(min) {
    if (min === null || min === undefined) return '—';
    const m = parseInt(min);
    if (m < 60) return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}

export default function ResponseTime({ filters, filterOptions, rows, avgResponse }) {
    return (
        <>
            <Head title="Response Time Report" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/response-time' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/response-time" />

            <div className="row g-3 mb-4">
                <div className="col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon amber"><span className="material-icons">timer</span></div>
                        <div className="stat-label">Avg Response Time</div>
                        <div className="stat-value">{fmtMinutes(avgResponse)}</div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">people</span></div>
                        <div className="stat-label">Leads Analysed</div>
                        <div className="stat-value">{(rows ?? []).length}</div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">check_circle</span></div>
                        <div className="stat-label">With Response</div>
                        <div className="stat-value">{(rows ?? []).filter(r => r.response_minutes !== null).length}</div>
                    </div>
                </div>
            </div>

            <div className="custom-table">
                <div className="table-header">
                    <h3>Lead Response Time</h3>
                    <a href="/manager/reports/export/response-time/excel" className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export CSV
                    </a>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>Lead Code</th>
                                <th>Lead Name</th>
                                <th>Telecaller</th>
                                <th>Created At</th>
                                <th>First Response</th>
                                <th>Response Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(rows ?? []).length === 0 ? (
                                <tr><td colSpan={6} className="text-center py-5 text-muted">No data for selected period.</td></tr>
                            ) : rows.map((r, i) => {
                                const mins = r.response_minutes;
                                const badge = mins === null ? 'bg-secondary'
                                    : mins <= 30 ? 'bg-success'
                                    : mins <= 120 ? 'bg-warning text-dark'
                                    : 'bg-danger';
                                return (
                                    <tr key={i}>
                                        <td>{r.lead_code}</td>
                                        <td className="fw-semibold">{r.lead_name}</td>
                                        <td>{r.telecaller}</td>
                                        <td className="text-muted" style={{ fontSize: 12 }}>{r.created_at}</td>
                                        <td className="text-muted" style={{ fontSize: 12 }}>{r.first_response_at ?? '—'}</td>
                                        <td>
                                            <span className={`badge ${badge}`}>{fmtMinutes(mins)}</span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
