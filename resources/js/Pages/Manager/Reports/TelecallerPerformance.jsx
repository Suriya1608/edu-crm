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

export default function TelecallerPerformance({ filters, filterOptions, rows }) {
    return (
        <>
            <Head title="Telecaller Performance" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/telecaller-performance' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/telecaller-performance" />

            <div className="custom-table">
                <div className="table-header">
                    <h3>Telecaller Performance</h3>
                    <a href="/manager/reports/export/telecaller-performance/excel" className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export CSV
                    </a>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>Telecaller</th>
                                <th>Assigned Leads</th>
                                <th>Calls Made</th>
                                <th>Avg Talk Time</th>
                                <th>Follow-ups</th>
                                <th>Converted</th>
                                <th>Efficiency Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(rows ?? []).length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-5 text-muted">No data for selected period.</td></tr>
                            ) : rows.map((r, i) => (
                                <tr key={i}>
                                    <td className="fw-semibold">{r.name}</td>
                                    <td>{r.assigned}</td>
                                    <td>{r.calls}</td>
                                    <td>{r.avg_talk_time}</td>
                                    <td>{r.followups}</td>
                                    <td><span className="badge bg-success">{r.converted}</span></td>
                                    <td><span className="badge bg-primary">{r.efficiency_score}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
