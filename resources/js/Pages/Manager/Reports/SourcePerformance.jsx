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

export default function SourcePerformance({ filters, filterOptions, rows }) {
    return (
        <>
            <Head title="Source Performance" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/source-performance' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/source-performance" />

            <div className="custom-table">
                <div className="table-header">
                    <h3>Source Performance</h3>
                    <a href="/manager/reports/export/source-performance/excel" className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export CSV
                    </a>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr><th>Source</th><th>Total Leads</th><th>Converted</th><th>Conversion Rate</th></tr>
                        </thead>
                        <tbody>
                            {(rows ?? []).length === 0 ? (
                                <tr><td colSpan={4} className="text-center py-5 text-muted">No data for selected period.</td></tr>
                            ) : rows.map((r, i) => (
                                <tr key={i}>
                                    <td className="fw-semibold">{r.source || '—'}</td>
                                    <td>{r.total_leads}</td>
                                    <td><span className="badge bg-success">{r.converted_leads}</span></td>
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <div className="progress flex-grow-1" style={{ height: 6 }}>
                                                <div className="progress-bar bg-primary" style={{ width: `${r.conversion_rate}%` }} />
                                            </div>
                                            <span style={{ fontSize: 12, whiteSpace: 'nowrap' }}>{r.conversion_rate}%</span>
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
