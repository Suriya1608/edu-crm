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

const STATUS_COLORS = {
    new: '#6366f1', assigned: '#0891b2', contacted: '#475569',
    interested: '#16a34a', not_interested: '#dc2626',
    converted: '#15803d', follow_up: '#92400e',
};

export default function Conversion({ filters, filterOptions, statusRows, teleRows }) {
    return (
        <>
            <Head title="Conversion Report" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/conversion' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/conversion" />

            <div className="row g-4">
                {/* Status breakdown */}
                <div className="col-md-5">
                    <div className="custom-table">
                        <div className="table-header">
                            <h3>Lead Status Breakdown</h3>
                            <a href="/manager/reports/export/conversion/excel" className="btn btn-sm btn-outline-success">
                                <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>CSV
                            </a>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0">
                                <thead><tr><th>Status</th><th>Count</th></tr></thead>
                                <tbody>
                                    {(statusRows ?? []).length === 0 ? (
                                        <tr><td colSpan={2} className="text-center py-4 text-muted">No data</td></tr>
                                    ) : statusRows.map((r, i) => (
                                        <tr key={i}>
                                            <td>
                                                <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: STATUS_COLORS[r.status] ?? '#94a3b8', marginRight: 6 }} />
                                                {r.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                            </td>
                                            <td className="fw-semibold">{r.total}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Telecaller conversion */}
                <div className="col-md-7">
                    <div className="custom-table">
                        <div className="table-header"><h3>Conversion by Telecaller</h3></div>
                        <div className="table-responsive">
                            <table className="table mb-0">
                                <thead><tr><th>Telecaller</th><th>Total Leads</th><th>Converted</th><th>Rate</th></tr></thead>
                                <tbody>
                                    {(teleRows ?? []).length === 0 ? (
                                        <tr><td colSpan={4} className="text-center py-4 text-muted">No data</td></tr>
                                    ) : teleRows.map((r, i) => (
                                        <tr key={i}>
                                            <td className="fw-semibold">{r.name}</td>
                                            <td>{r.total}</td>
                                            <td><span className="badge bg-success">{r.converted}</span></td>
                                            <td>
                                                <div className="d-flex align-items-center gap-2">
                                                    <div className="progress flex-grow-1" style={{ height: 6 }}>
                                                        <div className="progress-bar bg-success" style={{ width: `${r.rate}%` }} />
                                                    </div>
                                                    <span style={{ fontSize: 12, whiteSpace: 'nowrap' }}>{r.rate}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
