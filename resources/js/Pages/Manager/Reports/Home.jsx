import { Head, Link } from '@inertiajs/react';
import ReportFilters from './_Filters';

const NAV = [
    { href: '/manager/reports',                  label: 'Overview'              },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance' },
    { href: '/manager/reports/conversion',       label: 'Conversion'            },
    { href: '/manager/reports/source-performance', label: 'Source Performance'  },
    { href: '/manager/reports/period',           label: 'Period Analysis'       },
    { href: '/manager/reports/response-time',    label: 'Response Time'         },
    { href: '/manager/reports/call-efficiency',  label: 'Call Efficiency'       },
];

function ReportNav({ active }) {
    return (
        <div className="d-flex gap-2 flex-wrap mb-4">
            {NAV.map(n => (
                <Link key={n.href} href={n.href}
                    className={`btn btn-sm ${active === n.href ? 'btn-primary' : 'btn-outline-secondary'}`}>
                    {n.label}
                </Link>
            ))}
        </div>
    );
}

export function ReportNavBar({ active }) { return <ReportNav active={active} />; }

export default function Home({ filters, filterOptions, totalLeads, contactedLeads, convertedLeads, conversionRate, activeTelecallers, funnel, sourceRows, telecallerRows }) {
    return (
        <>
            <Head title="Reports Overview" />
            <ReportNav active="/manager/reports" />
            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports" />

            {/* ── KPI cards ─────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label: 'Total Leads',       value: totalLeads,       icon: 'people',        cls: 'blue'  },
                    { label: 'Contacted',         value: contactedLeads,   icon: 'phone_in_talk', cls: 'amber' },
                    { label: 'Converted',         value: convertedLeads,   icon: 'check_circle',  cls: 'green' },
                    { label: 'Conversion Rate',   value: `${conversionRate}%`, icon: 'trending_up', cls: 'green' },
                    { label: 'Active Telecallers',value: activeTelecallers, icon: 'support_agent', cls: 'blue' },
                ].map(s => (
                    <div className="col-6 col-md-3" key={s.label}>
                        <div className="stat-card">
                            <div className={`stat-icon ${s.cls}`}><span className="material-icons">{s.icon}</span></div>
                            <div className="stat-label">{s.label}</div>
                            <div className="stat-value">{s.value}</div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="row g-4">
                {/* Funnel */}
                <div className="col-md-5">
                    <div className="chart-card h-100">
                        <h6 className="fw-semibold mb-3">Lead Funnel</h6>
                        {[
                            { label: 'New / Assigned', value: funnel?.new ?? 0,        color: '#6366f1' },
                            { label: 'Contacted',      value: funnel?.contacted ?? 0,  color: '#f59e0b' },
                            { label: 'Interested',     value: funnel?.interested ?? 0, color: '#10b981' },
                            { label: 'Converted',      value: funnel?.converted ?? 0,  color: '#22c55e' },
                        ].map(row => (
                            <div key={row.label} className="mb-3">
                                <div className="d-flex justify-content-between mb-1" style={{ fontSize: 13 }}>
                                    <span>{row.label}</span>
                                    <span className="fw-semibold">{row.value}</span>
                                </div>
                                <div className="progress" style={{ height: 8 }}>
                                    <div className="progress-bar" style={{ width: `${totalLeads > 0 ? (row.value / totalLeads) * 100 : 0}%`, background: row.color }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Source breakdown */}
                <div className="col-md-7">
                    <div className="chart-card h-100">
                        <h6 className="fw-semibold mb-3">Leads by Source</h6>
                        <div className="table-responsive">
                            <table className="table table-sm mb-0">
                                <thead className="table-light">
                                    <tr><th>Source</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    {(sourceRows ?? []).length === 0
                                        ? <tr><td colSpan={2} className="text-center text-muted py-3">No data</td></tr>
                                        : (sourceRows ?? []).map((r, i) => (
                                            <tr key={i}><td>{r.source || '—'}</td><td className="fw-semibold">{r.total}</td></tr>
                                        ))
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Telecaller rows */}
            {(telecallerRows ?? []).length > 0 && (
                <div className="custom-table mt-4">
                    <div className="table-header"><h3>Telecaller Summary</h3></div>
                    <div className="table-responsive">
                        <table className="table mb-0">
                            <thead>
                                <tr><th>Telecaller</th><th>Assigned</th><th>Calls</th><th>Avg Talk</th><th>Follow-ups</th><th>Converted</th><th>Score</th></tr>
                            </thead>
                            <tbody>
                                {telecallerRows.map((r, i) => (
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
            )}
        </>
    );
}
